# PrimeVueKit

Paquete local de la aplicación, instalado mediante un repositorio `path` de Composer
(`servicios-linea-once/primevuekit`). Su código se autocarga bajo el namespace `PrimeVueKit\`.

## Estado

Service provider con auto-discovery, configuración publicable, el comando
`primevuekit:install` para verificar e instalar el stack de frontend, y suite de pruebas con
Pest + Testbench. Todavía no expone componentes propios de PrimeVue.

## Instalación en la aplicación

Ya está declarado en el `composer.json` de la raíz:

```json
"repositories": [
    { "type": "path", "url": "servicios-linea-once/primevuekit" }
],
"require": {
    "primevuekit/primevuekit": "@dev"
}
```

Composer crea un enlace simbólico, así que cualquier cambio en `servicios-linea-once/primevuekit/src`
se refleja de inmediato en la aplicación.

## `primevuekit:install`

Verifica e instala el stack de frontend (Vue 3 + Inertia + PrimeVue + Ziggy) y aplica el
wiring mínimo sobre la aplicación.

```bash
# Diagnóstico: no escribe nada y sale con código 1 si falta algo (apto para CI)
php artisan primevuekit:install --check

# Instalación completa
php artisan primevuekit:install
```

| Opción | Efecto |
| --- | --- |
| `--check` | Sólo diagnostica. Sin efectos secundarios. Salida 1 si falta algo |
| `--primevue=4\|5` | Línea de PrimeVue. `4` (por defecto) es MIT; `5` usa la licencia PrimeUI |
| `--composer=<ruta>` | Ruta absoluta al binario de Composer (por defecto, el `composer` del PATH) |
| `--force` | Sobrescribe los archivos de wiring ya existentes |
| `--no-scaffold` | Instala dependencias sin tocar archivos de la aplicación |
| `--no-demo` | Omite la ruta y la página Inertia de demostración |

### Qué instala

Composer: `inertiajs/inertia-laravel:^3.3` y `tightenco/ziggy:^2.6`.

npm: `vue`, `@inertiajs/vue3`, `ziggy-js`, `primevue`, `@primeuix/themes`, `primeicons` y,
como dependencias de desarrollo, `@vitejs/plugin-vue` y `tailwindcss-primeui`.

Las versiones viven en un único sitio,
[`src/Installer/DependencySet.php`](src/Installer/DependencySet.php); es ahí donde se suben.

### Las dos líneas de PrimeVue

`npm install primevue` resuelve hoy a la 5.x, que **ya no es MIT**: se distribuye bajo la
licencia PrimeUI y exige una clave de licencia. La última línea MIT es la 4.x. El comando fija
siempre la versión y mantiene coherente la tripleta, porque `primevue` y `@primeuix/themes`
comparten `@primeuix/styled` y mezclar majors rompe el modo *styled* en silencio:

| Línea | `primevue` | `@primeuix/themes` | `primeicons` | Licencia |
| --- | --- | --- | --- | --- |
| `--primevue=4` (por defecto) | `^4.5.5` | `^2.0.3` | `^7.0.0` | MIT |
| `--primevue=5` | `^5.0.1` | `^3.0.0` | `^8.0.0` | PrimeUI, clave obligatoria |

Con `--primevue=5` el comando avisa de las condiciones de la licencia y pide confirmación,
pero **no** configura la clave.

### Wiring que aplica

Todas las operaciones son idempotentes; si un archivo no encaja con el patrón esperado el
comando avisa con la instrucción manual en lugar de dejarlo a medias.

| Archivo | Cambio |
| --- | --- |
| `vite.config.js` | Registra `@vitejs/plugin-vue` |
| `resources/js/app.js` | Bootstrap de Inertia + PrimeVue (preset Aura) + ZiggyVue, conservando el `import './echo'` |
| `resources/css/app.css` | Añade `@import 'tailwindcss-primeui';` |
| `resources/views/app.blade.php` | Vista raíz con `@routes`, `@vite` y los componentes `<x-inertia::head />` / `<x-inertia::app />` |
| `bootstrap/app.php` | Añade `HandleInertiaRequests` al grupo `web` |
| `routes/web.php` | Ruta `primevuekit.demo` en `/primevuekit` |
| `resources/js/Pages/PrimeVueKit/Welcome.vue` | Página Inertia de demostración |

`bootstrap/app.php` sólo se toca si `app/Http/Middleware/HandleInertiaRequests.php` existe:
referenciar una clase inexistente rompería todas las peticiones.

### Node dentro de Docker

El contenedor `app` (`php:8.4-fpm`) no trae Node. Cuando el comando no encuentra el binario
no falla: imprime el comando equivalente para el servicio `vite`.

```bash
docker compose run --rm vite npm install vue@^3.5.42 ... --ignore-scripts
```

Como `node_modules` vive en un volumen de Docker, el diagnóstico distingue *declarada* (está
en `package.json` / `composer.json`) de *instalada* (presente en `node_modules` / `vendor`), y
sólo considera un problema lo primero.

### CORS del dev server de Vite

La aplicación se sirve en `:8000` y el dev server de Vite en `:5173`, así que cargar
`resources/js/app.js` es una petición **cross-origin**. Vite 8 endureció esto a raíz de
CVE-2025-24010: sólo responde con `Access-Control-Allow-Origin` a orígenes que conoce. Si el
navegador muestra `has been blocked by CORS policy` al cargar `app.js`, hay que declarar el
origen de la aplicación en `vite.config.js`:

```js
server: {
    cors: { origin: [env.APP_URL, 'http://localhost:8000'] },
}
```

Caso aparte: si el origen del error es **`null`**, la página no está en una pestaña normal sino
dentro de un documento embebido (`about:srcdoc`: el panel de vista previa del IDE, un iframe en
sandbox, `file://`). Lo sano es abrirla en una pestaña de verdad; si necesitas la vista previa,
añade `'null'` a la lista de orígenes, detrás de una variable de entorno y sólo en desarrollo:
permite que cualquier contexto aislado lea el código fuente del dev server.

## `primevuekit:auth`

Instala la autenticación. Menú interactivo (Laravel Prompts) para elegir el backend; **el
segundo factor por correo (OTP) y el de aplicación (TOTP) se instalan siempre**, en las tres
estrategias.

```bash
php artisan primevuekit:auth              # pregunta la estrategia
php artisan primevuekit:auth --check      # diagnóstico, salida 1 si falta algo
php artisan primevuekit:auth --strategy=kit
php artisan migrate
```

| Estrategia | Backend | Dónde vive el código |
| --- | --- | --- |
| `fortify` | `laravel/fortify ^1.39` (login, registro, reset, verificación y TOTP) | `app/Actions` + provider publicados por `fortify:install` |
| `kit` | Rutas, controladores y páginas del paquete | En el paquete, se actualiza con él |
| `manual` | El mismo código, publicado | En tu aplicación, para editarlo |

Exige que `primevuekit:install` se haya ejecutado antes: sin Inertia y Vue no hay dónde
montar las páginas.

### Por qué no hay opción de Breeze

`laravel/breeze` se declara a sí mismo legacy — su README en la rama `2.x` dice *"This starter
kit is for Laravel 11.x and prior"* — y `laravel.com/docs/13.x/starter-kits` ya no lo menciona.
Además `breeze:install vue` fija `inertiajs/inertia-laravel:^2.0`, `@inertiajs/vue3:^2.0.0` y
`tailwindcss:^3.2.1`, y sobrescribe `resources/js/app.js`, `vite.config.js`,
`resources/css/app.css`, `routes/web.php`, `app.blade.php` y `HandleInertiaRequests.php`: es
decir, destruiría el stack que instaló `primevuekit:install`. El preset `kit` cubre el mismo
terreno sin degradar nada.

### Segundo factor

- **TOTP** (RFC 6238) con `pragmarx/google2fa` y QR en SVG con `bacon/bacon-qr-code`, las mismas
  librerías que usa Fortify, así que no hay conflicto de versiones. El secreto se guarda cifrado
  y el factor no se activa hasta confirmarlo con un código válido. Hay 8 códigos de recuperación
  cifrados que se consumen de uno en uno.
- **OTP por correo**: código de 6 dígitos generado con `random_int`, **guardado hasheado**,
  válido 5 minutos, 5 intentos como máximo, reenvío limitado a 1 por minuto y 5 por hora. Emitir
  uno nuevo invalida el anterior. La notificación va en cola.
- Columnas: las tres de Fortify (`two_factor_secret`, `two_factor_recovery_codes`,
  `two_factor_confirmed_at`) más `two_factor_last_used_counter` y `email_otp_enabled`, y la tabla
  `email_otp_codes`. La migración es condicional, así que convive con la de Fortify.
- Todo es configurable en `config/primevuekit.php`, sección `auth`.

`bacon/bacon-qr-code` y su dependencia `dasprid/enum` son BSD-2-Clause (permisiva y compatible
con MIT, pero no MIT); el resto de dependencias del kit son MIT.

### Rutas y páginas

Las rutas se enganchan desde `routes/web.php` de la aplicación, como hace Breeze, y no desde
el service provider: así una app que sólo quiera los componentes de PrimeVue no acaba con un
`/login` que no ha pedido. El comando añade `\PrimeVueKit\Auth\AuthRoutes::register();` (preset
`kit`) o `require __DIR__.'/auth.php';` (modo `manual`). Si detecta la línea de la otra
estrategia avisa en vez de registrar las rutas dos veces.

| Ruta | Nombre | Página |
| --- | --- | --- |
| `GET/POST /login`, `POST /logout` | `login`, `logout` | `Auth/Login` |
| `GET/POST /register` | `register` | `Auth/Register` |
| `GET/POST /forgot-password`, `/reset-password/{token}` | `password.*` | `Auth/ForgotPassword`, `Auth/ResetPassword` |
| `GET /verify-email`, `/verify-email/{id}/{hash}` | `verification.*` | `Auth/VerifyEmail` |
| `GET/POST /confirm-password` | `password.confirm` | `Auth/ConfirmPassword` |
| `GET/POST /two-factor-challenge` | `two-factor.challenge` | `Auth/TwoFactorChallenge` |
| `GET/POST /otp-challenge`, `POST /otp-challenge/resend` | `otp.challenge`, `otp.resend` | `Auth/EmailOtpChallenge` |
| `GET/POST/DELETE /user/two-factor`, `/user/email-otp` | `two-factor.*`, `email-otp.*` | `Auth/TwoFactor` |

Las pantallas de reto están protegidas por `EnsureChallengeIsPending`: sin credenciales
validadas redirigen al login. El enrolamiento va detrás de `password.confirm`.

El modelo de usuario recibe los traits **y** los contratos `SupportsEmailOtp` y `SupportsTotp`,
de forma que los controladores comprueban capacidades con `instanceof` en lugar de
`method_exists`. Con `--strategy=fortify` no se añade `SupportsTotp`: ese factor es de Fortify.

### Panel y perfil

Al terminar, el comando copia en la aplicación un punto de partida para la zona
autenticada. Esto se publica **siempre**, en las tres estrategias: un panel y un perfil son
de cada proyecto y se editan desde el primer día, así que no tiene sentido servirlos desde el
paquete. Los stubs viven en [`stubs/dashboard/`](stubs/dashboard/) y ya traen el namespace
`App\`, no hay nada que reescribir. Con `--no-dashboard` se omite.

```
app/Http/Controllers/DashboardController.php
app/Http/Controllers/Profile/{ProfileController,PasswordController}.php
app/Http/Requests/Profile/{UpdateProfileRequest,UpdatePasswordRequest}.php
routes/dashboard.php                              → require desde routes/web.php
resources/js/Layouts/AppLayout.vue                → Menubar + menú de usuario
resources/js/Pages/Dashboard.vue                  → estado de la cuenta y de los factores
resources/js/Pages/Profile/Edit.vue
resources/js/Pages/Profile/Partials/{UpdateProfileForm,UpdatePasswordForm,DeleteAccountForm}.vue
```

Rutas: `dashboard` (tras `auth` + `verified`), `profile.edit`, `profile.update`,
`profile.destroy` y `profile.password.update`.

El publicador también añade las props compartidas que el layout necesita en
`HandleInertiaRequests::share()`. Comparte **sólo cuatro atributos** del usuario:

```php
'user' => $request->user()?->only('id', 'name', 'email', 'email_verified_at'),
```

Compartir el modelo entero enviaría `two_factor_secret` y `two_factor_recovery_codes` al
navegador, porque no están en `$hidden`.

Cambiar el correo en el perfil invalida la verificación y reenvía el enlace. Eliminar la
cuenta exige la contraseña actual, cierra la sesión y la invalida.

Para que el login lleve al panel en vez de a `/`, pon `PRIMEVUEKIT_HOME=/dashboard` en `.env`.

### Aspecto de las pantallas

Las 9 páginas usan [`layouts/AuthCard.vue`](resources/js/layouts/AuthCard.vue): una tarjeta partida en dos, con
panel de marca en degradado a la izquierda —oculto por debajo de `lg`— y el formulario a la derecha. El degradado
sale de los tokens del tema (`--p-primary-500/700/900`), así que cambiar la paleta cambia toda la UI sin tocar
ninguna página.

- [`components/AuthIllustration.vue`](resources/js/components/AuthIllustration.vue) — escudo, cubos y esferas en
  SVG inline con animación de flotación, que se desactiva con `prefers-reduced-motion`. Sin assets binarios.
- [`components/AuthSubmit.vue`](resources/js/components/AuthSubmit.vue) — el botón principal en degradado,
  centralizado para no repetir la cadena de clases nueve veces.
- Los campos usan `IftaLabel` (etiqueta dentro de la caja) más `IconField` + `InputIcon` para el icono a la
  derecha. Las contraseñas usan `Password toggle-mask`, que ya trae el ojo, y los códigos `InputOtp`.

La paleta va en el preset de PrimeVue, no en clases fijas. El stub
[`stubs/app.js.stub`](stubs/app.js.stub) la define con `definePreset(Aura, …)`; para cambiarla, edita los tonos
`primary` ahí (o en el `resources/js/app.js` ya generado).

## Publicar la configuración

```bash
php artisan vendor:publish --tag=primevuekit-config
```
