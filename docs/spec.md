# Spec — App de prueba para `placetopay/applepay-sdk` (rama `release/1.0.0`)

## Por qué

`applepay-sdk` está terminado pero nunca se ha ejecutado fuera de su propia suite. Sus 73 tests
prueban el SDK contra sí mismo: mismos mocks, misma llave de juguete, mismo proceso. Lo que falta
comprobar es lo otro — que un integrador real pueda instalarlo, pasarle su configuración desde un
`.env`, y que **esa configuración llegue de verdad hasta donde tiene que llegar**: el `timeout` al
cliente Guzzle, la llave privada al ECDH, el certificado mTLS al POST de validación de comerciante,
el logger sanitizado al canal de logs de la app.

Este proyecto es esa comprobación. No es una demo comercial ni una integración de producción: es un
banco de pruebas de Laravel, lo más delgado posible, que hace visible lo que el SDK hace por dentro.

Referencia directa: `/Users/ivanlopez/Projects/test-google-sdk`, que cumple el mismo papel para
`googlepay-sdk` y que se instala igual, desde el mismo repositorio privado. Se copia su forma (controladores planos, vistas Blade con `<style>` embebido, sin
build de frontend) y se corrige lo que allá quedó a medias.

## Alcance

**Dentro:**

1. Instalar el SDK desde el checkout local en `release/1.0.0` y que `composer install` funcione.
2. Descifrar un token real de Apple pegado en un formulario.
3. Descifrar un token sintético generado por `ApplePayTokenGeneratorMock` **con la llave del `.env`**,
   incluyendo los escenarios de fallo (`makeTampered`, `makeWithInvalidSignature`, `makeExpired`).
4. Ejecutar `validateMerchant()` contra Apple con certificado mTLS, y contra `ApplePayClientMock`.
5. Una pantalla que muestre la configuración efectiva resuelta por `Settings`, enmascarada.
6. Tests de Pest que cubran los caminos anteriores sin salir a la red.

**Fuera:** frontend de Apple Pay JS, botón de pago, dominio verificado con Apple, base de datos,
autenticación, despliegue. Nada se persiste: el token entra por POST, se procesa y se muestra.

## Restricciones que ya se verificaron

| Hecho | Consecuencia |
|---|---|
| El repo privado no tiene versiones estables de `applepay-sdk` (no hay tags todavía), pero tras correr `hook.php?repo=placetopay/applepay-sdk` sí publica `dev-release/1.0.0` en el canal `~dev`, apuntando a `cf79381` | Se instala desde el repositorio privado con la constraint `dev-release/1.0.0`. No hace falta `path` repository ni tocar `minimum-stability`: una constraint dev en la raíz fija su propio flag de estabilidad. |
| `placetopay/base` sí responde en el repo privado | La dependencia transitiva se resuelve normal; basta añadir el repositorio `composer` además del `path`. |
| El SDK exige `php: ^8.5`; este proyecto declara `php: ^8.3` | Hay que subir el `require.php` del proyecto a `^8.5`. El binario local ya es 8.5.8. |
| `Illuminate\Contracts\Cache\Repository extends Psr\SimpleCache\CacheInterface` | El caché PSR-16 que pide el SDK es `Cache::store()` a secas. El README de googlepay dice `->getStore()`, que devuelve el *store* y **no** es PSR-16 — no copiar eso. |
| El SDK no empaqueta el Apple Root CA G3: lo descarga de `apple.com` y lo cachea con el TTL del `Cache-Control` | Sin caché configurado, cada instancia de `ApplePay` sale a la red. La app debe pasar `Cache::store()` siempre, y eso mismo se vuelve algo observable en la pantalla de configuración. |
| `ApplePayTokenGeneratorMock` firma con una cadena sintética, no con la de Apple | En la pantalla de mocks es **obligatorio** pasar `rootCertificate: ApplePayTokenGeneratorMock::rootCertificate()`. Si no, el token se rechaza antes de descifrarse. Es la trampa nº1 del SDK y la app tiene que dejarla evidente, no esconderla. |
| `validateMerchant()` lanza `InvalidSettingsException::missingMerchantCertificate()` si faltan `certPath`/`certKeyPath` | Esa ruta necesita el Merchant Identity Certificate, que es distinto del Payment Processing. La pantalla debe decirlo. |
| Un `httpClient` inyectado se salta `buildHttpClient()` | Pierde timeouts y middleware de log. Donde se use el mock, se arma con `$settings->buildHttpClient(['handler' => $mock])`. |

## Superficie de configuración

Todo lo que acepta `Settings::fromArray()` se expone en `config/applepay.php` desde el `.env`. Esta
tabla es el contrato de la app; si algo aquí no se puede comprobar desde el navegador, la app está
incompleta.

| Clave del SDK | `.env` | Default | Cómo se comprueba |
|---|---|---|---|
| `merchantId` | `APPLEPAY_MERCHANT_ID` | — | Campo del formulario; sin él, `InvalidSettingsException` |
| `privateKey` | `APPLEPAY_PRIVATE_KEY` | — | Descifrado correcto; con llave ajena falla el `publicKeyHash` |
| `rootCertificate` | `APPLEPAY_ROOT_CERTIFICATE` | `''` | Pantalla de config: corta la cascada |
| `rootCertificateUrl` | `APPLEPAY_ROOT_CERTIFICATE_URL` | URL de Apple | Log HTTP del `GET` |
| `expirationTime` | `APPLEPAY_EXPIRATION_TIME` | `300` | Escenario `makeExpired` |
| `timeout` | `APPLEPAY_TIMEOUT` | `10` | Pantalla de config |
| `connectTimeout` | `APPLEPAY_CONNECT_TIMEOUT` | `5` | Pantalla de config |
| `certPath` | `APPLEPAY_CERT_PATH` | `null` | Validación de comerciante |
| `certKeyPath` | `APPLEPAY_CERT_KEY_PATH` | `null` | Validación de comerciante |
| `certKeyPassword` | `APPLEPAY_CERT_KEY_PASSWORD` | `null` | Validación de comerciante |
| `httpLogger.enabled` | `APPLEPAY_HTTP_LOGGER` | `false` | Entradas en `storage/logs/laravel.log` |
| `logger` | — | `logger()` | Log con PAN enmascarado |
| `cache` | — | `Cache::store()` | Segunda petición no descarga el CA |

La llave privada va en el `.env` en una sola línea con `\n`, y `config/applepay.php` la reconstituye
con `str_replace('\n', "\n", ...)`, igual que en `test-google-sdk`.

## Pantallas

Cuatro rutas, un controlador cada una, todas con el mismo esqueleto: `GET` muestra el formulario,
`POST` procesa y redirige con `session()`.

### `GET|POST /` — Token real

Textarea con el JSON del token capturado de un dispositivo, más el `merchantId`. Usa la
configuración del `.env` tal cual, con el Root CA descargado de Apple. Es el camino de producción.

Muestra: `token()`, `expiration()`, `franchise()` y el array `additional()` completo
(`cryptogram`, `eci`, `walletID`, `authenticationMethod`).

### `GET|POST /mock` — Token sintético

Un `select` con los cuatro escenarios y el mismo `merchantId`. Genera el token con
`ApplePayTokenGeneratorMock::make*($merchantId, $privateKey)` y lo descifra con una instancia de
`ApplePay` que lleva `rootCertificate` sintético. Muestra el token generado junto al resultado, para
que se vea qué se le está pasando al SDK.

Los tres escenarios de fallo deben mostrar el mensaje exacto del SDK, no uno propio:
`Digest message does not match signed data`, `Invalid ECDSA signature`, y el de ventana de firma.

Un campo opcional de *overwrites* en JSON (`{"applicationPrimaryAccountNumber": "4111111111111111"}`)
se pasa como tercer argumento, para probar distintas franquicias sin tocar código.

### `GET|POST /merchant-validation` — Validación de comerciante

Campos: `validationUrl`, `domainName`, `displayName`, y un interruptor *real / mock*. En modo real
usa el mTLS del `.env`; en modo mock arma el cliente con
`$settings->buildHttpClient(['handler' => ApplePayClientMock::new()->pushMerchantSession([...])])`,
que es la forma correcta de conservar timeouts y logging.

Muestra el `MerchantSession::toArray()` completo, y si faltan los certificados, el mensaje de
`missingMerchantCertificate()` con la explicación de qué certificado es el que hace falta.

### `GET /config` — Configuración efectiva

Sin formulario. Construye `Settings::fromArray(config('applepay'))` y vuelca lo que resolvió:
timeouts, URL del CA, si hay `rootCertificate` fijado, si hay caché, si el logger HTTP está activo,
y el `sha256` de la llave pública derivada de `privateKey` — que es exactamente el valor contra el
que el SDK compara `header.publicKeyHash`, así que sirve para saber de antemano si un token dado es
para este comercio o para otro.

La llave privada nunca se imprime: solo su tipo de curva y su hash público. El certificado raíz se
muestra por *fingerprint* y fechas de validez, no en PEM.

## Tests

Pest, en `tests/Feature`, sin red. Cinco casos, que son las cinco afirmaciones que importan:

1. `/mock` con escenario válido devuelve un `BrandToken` con el PAN esperado.
2. Los tres escenarios de fallo devuelven cada uno su mensaje del SDK.
3. `/mock` con un `merchantId` distinto al configurado falla — prueba de que el `merchantId` viaja.
4. `/merchant-validation` en modo mock devuelve el `MerchantSession` de `ApplePayClientMock`.
5. `/merchant-validation` sin `certPath` configurado responde con el error de certificado faltante.

La llave de pruebas se genera en el propio test con `openssl_pkey_new` (EC P-256) y se inyecta con
`config()->set()`. No hay llaves reales en el repositorio ni en el `.env.example`.

## Criterios de aceptación

- `composer install` resuelve `placetopay/applepay-sdk dev-release/1.0.0` desde `repository.placetopay.com`.
- Las cuatro rutas funcionan con un `.env` copiado de `.env.example` más una llave EC P-256.
- Los cuatro escenarios de mock producen el resultado esperado, incluidos los tres fallos.
- Con `APPLEPAY_HTTP_LOGGER=true`, `storage/logs/laravel.log` contiene la petición al Root CA y la de
  validación de comerciante, con `merchantSessionIdentifier` enmascarado.
- Con el caché activo, la segunda petición de la misma sesión no vuelve a descargar el CA.
- `php artisan test` pasa en verde.
