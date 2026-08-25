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

0. El botón de Apple Pay y el flujo completo del navegador, hasta donde Apple lo permita sin dominio
   verificado.
1. Instalar el SDK desde el checkout local en `release/1.0.0` y que `composer install` funcione.
2. Descifrar un token real de Apple pegado en un formulario.
3. Descifrar un token sintético generado por `ApplePayTokenGeneratorMock` **con la llave del `.env`**,
   incluyendo los escenarios de fallo (`makeTampered`, `makeWithInvalidSignature`, `makeExpired`).
4. Ejecutar `validateMerchant()` contra Apple con certificado mTLS, y contra `ApplePayClientMock`.
5. Una pantalla que muestre la configuración efectiva resuelta por `Settings`, enmascarada.
6. Tests de Pest que cubran los caminos anteriores sin salir a la red.

**Fuera:** dominio verificado con Apple, base de datos, autenticación, despliegue. Nada se persiste: el token entra por POST, se procesa y se muestra.

## Restricciones que ya se verificaron

| Hecho | Consecuencia |
|---|---|
| El repo privado no tiene versiones estables de `applepay-sdk` (no hay tags todavía), pero tras correr `hook.php?repo=placetopay/applepay-sdk` sí publica `dev-release/1.0.0` en el canal `~dev`, apuntando a `cf79381` | Se instala desde el repositorio privado con la constraint `dev-release/1.0.0`. No hace falta `path` repository ni tocar `minimum-stability`: una constraint dev en la raíz fija su propio flag de estabilidad. |
| `placetopay/base` sí responde en el repo privado | La dependencia transitiva se resuelve normal; basta añadir el repositorio `composer` además del `path`. |
| El SDK exige `php: ^8.5`; este proyecto declara `php: ^8.3` | Hay que subir el `require.php` del proyecto a `^8.5`. El binario local ya es 8.5.8. |
| `Illuminate\Contracts\Cache\Repository extends Psr\SimpleCache\CacheInterface` | El caché PSR-16 que pide el SDK es `Cache::store()` a secas. El README de googlepay dice `->getStore()`, que devuelve el *store* y **no** es PSR-16 — no copiar eso. |
| El SDK no empaqueta el Apple Root CA G3: lo descarga de `apple.com` y lo cachea con el TTL del `Cache-Control` | Sin caché configurado, cada instancia de `ApplePay` sale a la red. La app debe pasar `Cache::store()` siempre, y eso mismo se vuelve algo observable en la pantalla de configuración. |
| `ApplePayTokenGeneratorMock` firma con una cadena sintética, no con la de Apple, y el root CA **no se puede configurar**: el SDK siempre lo descarga | En la pantalla de mocks hay que responder esa descarga con la raíz sintética: `httpClient: ApplePayClientMock::syntheticRootCertificate()`. Sin eso el token se rechaza antes de descifrarse. Es la trampa nº1 del SDK y la app tiene que dejarla evidente, no esconderla. |
| La cadena sintética se regenera **en cada proceso PHP**, pero un root cacheado sobrevive entre peticiones | La pantalla de mocks corre con `cache: null`. Si no, la raíz que cachea una petición rechaza el token de la siguiente con `Intermediate CA certificate is not signed by the Apple Root CA`. En una suite de tests no se ve, porque todo corre en un proceso. |
| `validateMerchant()` lanza `InvalidSettingsException::missingMerchantCertificate()` si faltan `certPath`/`certKeyPath` | Esa ruta necesita el Merchant Identity Certificate, que es distinto del Payment Processing. La pantalla debe decirlo. |
| Un `httpClient` inyectado se salta `buildHttpClient()` | Pierde timeouts y middleware de log: el cliente que pasas es el cliente que se usa, composición incluida. Donde importe conservarlos, se arma con `$settings->buildHttpClient(['handler' => $mock])`. |

## Superficie de configuración

Todo lo que acepta `Settings::fromArray()` se expone en `config/applepay.php` desde el `.env`. Esta
tabla es el contrato de la app; si algo aquí no se puede comprobar desde el navegador, la app está
incompleta.

| Clave del SDK | `.env` | Cómo se comprueba |
|---|---|---|
| `merchantId` | `APPLEPAY_MERCHANT_ID` | Campo del formulario; sin él, `InvalidSettingsException` |
| `privateKey` | `APPLEPAY_PRIVATE_KEY` | Descifrado correcto; con llave ajena falla el `publicKeyHash` |
| `certPath` | `APPLEPAY_CERT_PATH` | Validación de comerciante en modo real |
| `certKeyPath` | `APPLEPAY_CERT_KEY_PATH` | Validación de comerciante en modo real |
| `httpLogger.enabled` | `APPLEPAY_HTTP_LOGGER` | Entradas en `storage/logs/laravel.log` |
| `logger` | — | Resuelto con `logger()`; log con PAN enmascarado |
| `cache` | — | Resuelto con `Cache::store()`; segunda petición no descarga el CA |

Los demás ajustes del SDK — `expirationTime`, `timeout`, `connectTimeout`, `certKeyPassword` — **no**
se exponen en `config/applepay.php`. `Settings::fromArray()`
ya los defaultea, y repetir aquí `300`, `10` y `5` no probaría que la configuración viaja: probaría
que dos archivos dicen el mismo número. La pantalla `/config` los muestra igual, leídos de `Settings`,
que es donde de verdad valen. El día que haga falta divergir de un default, se añade esa clave sola.

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
`ApplePay` cuyo `httpClient` responde la descarga del root CA con la raíz sintética, y sin caché.
Muestra el token generado junto al resultado, para que se vea qué se le está pasando al SDK.

El escenario *expirado* es la excepción: devuelve un token real capturado de Apple, así que necesita
la raíz **real** para llegar a la comprobación de ventana de firma, que es lo que ese escenario
prueba. Esa es la respuesta por defecto de `ApplePayClientMock`.

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

### `GET|POST /pay` — Botón de Apple Pay

El flujo real de punta a punta, el único que produce un token genuino. `ApplePaySession` v3 en el
navegador; `POST /pay/validate-merchant` responde a `onvalidatemerchant` abriendo la sesión con el
Merchant Identity Certificate; `POST /pay/process` recibe `event.payment.token.paymentData` y lo
descifra.

El botón usa la apariencia nativa de Safari (`-webkit-appearance: -apple-pay-button`), no el web
component de Apple: evita depender de un script de su CDN para dibujar un rectángulo negro.

**Esta pantalla no se puede completar en local**, y la propia pantalla lo dice: arranca con un panel
de prerrequisitos que comprueba merchantId, llave privada, certificado mTLS, si el host es público y
si el archivo de verificación de dominio está publicado, más una fila que el JS rellena según si
`ApplePaySession` existe y si hay tarjeta en Wallet. Los tres bloqueos reales son de Apple, no del
código:

1. **Dominio público con TLS de confianza.** Apple no verifica un `.test`, y `ApplePaySession` exige
   HTTPS con certificado válido. Hace falta un host público — un túnel o un staging.
2. **Verificación del dominio con Apple.** Se registra en *Merchant IDs → Merchant Domains*, se
   descarga el archivo y se publica en `public/.well-known/apple-developer-merchantid-domain-association.txt`.
   Apple lo descarga por internet.
3. **Merchant Identity Certificate.** Es el que firma la sesión; sin él `onvalidatemerchant` falla.
   No es el Payment Processing Certificate.

Además hace falta Safari en macOS o iOS con una tarjeta aprovisionada en Wallet — sandbox exige una
cuenta de Sandbox Tester y las tarjetas de prueba de Apple.

### `GET /config` — Configuración efectiva

Sin formulario. Construye `Settings::fromArray(config('applepay'))` y vuelca lo que resolvió:
timeouts, la URL fija del CA, si hay caché, si el logger HTTP está activo,
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
