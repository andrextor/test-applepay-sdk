# Plan de implementación — App de prueba `applepay-sdk`

Spec: `docs/spec.md`. Ocho pasos, en orden. Cada uno deja algo verificable; si un paso no se puede
comprobar, no se pasa al siguiente.

Estado de partida: Laravel 13 recién instalado, PHP 8.5.8, sin rutas ni controladores propios.

---

## Paso 1 — Instalar el SDK desde el repositorio privado

`applepay-sdk` no tiene tags, así que el canal estable de Satis está vacío. Lo que sí publica, una
vez reindexado, es la rama: `dev-release/1.0.0`. Se reindexa con el hook, igual que se hizo con
`googlepay-sdk`:

```bash
curl -s "https://repository.placetopay.com/hook.php?repo=placetopay/applepay-sdk" | tail -3
curl -s "https://repository.placetopay.com/p2/placetopay/applepay-sdk~dev.json" | grep -o 'dev-release/1.0.0'
```

En `composer.json` del proyecto de prueba:

```json
"repositories": [
    { "type": "composer", "url": "https://repository.placetopay.com" }
],
"require": {
    "php": "^8.5",
    "placetopay/applepay-sdk": "dev-release/1.0.0"
}
```

No hace falta bajar `minimum-stability` a `dev`: una constraint explícitamente dev en la raíz fija su
propio flag de estabilidad, y el resto del árbol sigue exigiendo `stable`.

Satis no expone `dist` para este paquete, solo `source` en `bitbucket.org/placetopay/applepay-sdk`,
así que Composer instala por git y necesita la llave SSH de Bitbucket — la misma que ya usa el
checkout local.

```bash
composer require placetopay/applepay-sdk:dev-release/1.0.0 -W
```

**Verificación:** `composer show placetopay/applepay-sdk` reporta `dev-release/1.0.0` con la
referencia `cf79381`, y
`php artisan tinker --execute 'var_dump(class_exists("Placetopay\ApplepaySdk\ApplePay"));'`
imprime `true`.

> Cuando se tagee `1.0.0`, esto se cambia a `"^1.0"` y basta con volver a correr el hook. Mientras
> tanto, `composer update placetopay/applepay-sdk` trae el último commit de la rama, que es
> justamente lo que se quiere mientras `release/1.0.0` siga moviéndose.

---

## Paso 2 — `config/applepay.php` y `.env.example`

```bash
php artisan make:class --no-interaction # no aplica; el config file se crea a mano
```

`config/applepay.php` devuelve el array con las trece claves de la tabla de la spec. La llave privada
pasa por `str_replace('\n', "\n", env(...))`. `logger` y `cache` **no** salen del `.env`: se resuelven
en el controlador con `logger()` y `Cache::store()`.

`.env.example` documenta cada variable con un comentario de una línea y valores vacíos. Nada de
llaves reales; la del ejemplo es un placeholder.

**Verificación:** `php artisan config:show applepay` lista todas las claves con sus defaults.

---

## Paso 3 — Un helper que arme la configuración

Los cuatro controladores necesitan el mismo array. Antes de duplicarlo cuatro veces, un único método
estático en `app/Support/ApplePayConfig.php`:

```php
public static function make(string $merchantId, array $overrides = []): array
```

Toma `config('applepay')`, filtra los nulos, añade `logger` y `cache`, y aplica los `$overrides`
(que es por donde entra el `rootCertificate` sintético en la pantalla de mocks, y el `httpClient`
en la de validación).

Es la única abstracción del proyecto. No hay interfaz, ni service provider, ni contenedor: es una
función que arma un array.

**Verificación:** el paso 4 lo consume.

---

## Paso 4 — Ruta `/` (token real)

```bash
php artisan make:controller RealTokenController --no-interaction
```

`show()` devuelve la vista con lo que haya en sesión. `decrypt()` valida `token` y `merchantId`,
hace `json_decode` del token, construye `ApplePay` con `ApplePayConfig::make()`, llama a `decrypt()`
y redirige con el `BrandToken` desarmado en array. Un solo `catch (ApplepaySdkException $e)` — el
SDK ya los unifica bajo esa clase padre.

La vista `resources/views/real-token.blade.php` se calca del `test-form.blade.php` de
`test-google-sdk`: HTML plano con `<style>` embebido, sin Vite ni Tailwind, así no hace falta
`npm run build` para ver nada.

Un detalle que en googlepay quedó feo y aquí no se repite: los `error_log()` sueltos con trozos de la
llave privada. Si hace falta trazar, se usa `logger()`, que ya pasa por el sanitizador del SDK.

**Verificación:** sin `.env` configurado, la pantalla muestra el mensaje de `InvalidSettingsException`
en vez de reventar con un 500.

---

## Paso 5 — Ruta `/mock` (token sintético)

```bash
php artisan make:controller MockTokenController --no-interaction
```

Un `match` sobre el escenario:

```php
$token = match ($scenario) {
    'valid'     => ApplePayTokenGeneratorMock::makeValid($merchantId, $privateKey, $overwrites),
    'tampered'  => ApplePayTokenGeneratorMock::makeTampered($merchantId, $privateKey, $overwrites),
    'signature' => ApplePayTokenGeneratorMock::makeWithInvalidSignature($merchantId, $privateKey, $overwrites),
    'expired'   => ApplePayTokenGeneratorMock::makeExpired(),
};
```

`makeExpired()` no recibe argumentos: es un token real de Apple firmado en 2024. Que rompa la
simetría del `match` es correcto, no un descuido.

La instancia se arma con `ApplePayConfig::make($merchantId, ['rootCertificate' => ApplePayTokenGeneratorMock::rootCertificate()])`,
y la vista lo dice explícitamente en un aviso: sin ese override el token sintético se rechaza contra
el root real de Apple. Es la trampa que más tiempo hace perder y se documenta en pantalla.

Los *overwrites* llegan como JSON en un textarea opcional; si el `json_decode` falla, se rechaza en
la validación en vez de pasar basura al mock.

**Verificación:** los cuatro escenarios, a mano, en el navegador. El válido devuelve un PAN; los tres
fallos devuelven los mensajes literales del SDK.

---

## Paso 6 — Ruta `/merchant-validation`

```bash
php artisan make:controller MerchantValidationController --no-interaction
```

Construye un `MerchantValidationRequest::fromArray()` con los tres campos del formulario y llama a
`validateMerchant()`. En modo mock, el `httpClient` se arma como manda el README:

```php
$settings = Settings::fromArray(ApplePayConfig::make($merchantId));
$client = $settings->buildHttpClient(['handler' => ApplePayClientMock::new()->pushMerchantSession()]);
```

y ese cliente entra por `$overrides['httpClient']`. Pasar `ApplePayClientMock::success()` directo
sería más corto pero perdería timeouts y el middleware de log, que son justo dos de las cosas que
esta app existe para comprobar.

**Verificación:** modo mock devuelve el `MerchantSession` completo. Modo real sin certificados
devuelve el mensaje de `missingMerchantCertificate()`.

---

## Paso 7 — Ruta `/config`

```bash
php artisan make:controller ConfigController --no-interaction
```

Solo `show()`. Construye `Settings`, y de la llave privada deriva la pública con `openssl_pkey_get_public`
para imprimir su `sha256` — el mismo valor que el SDK compara contra `header.publicKeyHash`. Del
certificado raíz, resuelto con `resolveRootCertificate()`, imprime `openssl_x509_parse()`: sujeto,
fechas y fingerprint.

Nada de PEM completo ni de llave privada en pantalla, ni siquiera truncada.

**Verificación:** recargar dos veces con el caché activo; la segunda vez no aparece el `GET` a
`apple.com` en el log HTTP.

---

## Paso 8 — Tests

```bash
php artisan make:test ApplePayMockTokenTest --pest --no-interaction
php artisan make:test MerchantValidationTest --pest --no-interaction
```

Los cinco casos de la spec. La llave EC P-256 se genera en un helper de `tests/Pest.php` con
`openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC])` y se
inyecta con `config()->set('applepay.private_key', $pem)`. Ninguna prueba sale a la red: los
escenarios de mock traen su propio `rootCertificate`, y la validación de comerciante usa
`ApplePayClientMock`.

```bash
php artisan test --compact
```

---

## Cierre

```bash
vendor/bin/pint --dirty --format agent
```

Commit por paso, o uno solo al final — pero antes de subir, `.env` fuera del repositorio y
`.env.example` sin una sola llave real dentro.

## Lo que este plan deja fuera a propósito

- **Botón de Apple Pay JS.** Requiere dominio verificado con Apple y certificado de comerciante. Se
  añade cuando haya que probar la validación de sesión de punta a punta, no antes.
- **Persistencia de tokens.** Nada se guarda: son datos de tarjeta. Añadirlo sería empeorarlo.
- **Un service provider que registre `ApplePay` en el contenedor.** Cuatro controladores que llaman a
  un método estático no justifican un singleton; en cada pantalla la configuración es distinta, que
  es el caso exacto en el que un singleton estorba.
