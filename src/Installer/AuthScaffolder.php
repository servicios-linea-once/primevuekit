<?php

declare(strict_types=1);

namespace PrimeVueKit\Installer;

use PrimeVueKit\Enums\AuthStrategy;
use PrimeVueKit\Enums\ScaffoldOutcome;
use PrimeVueKit\Installer\Concerns\EditsFiles;

/**
 * Conecta la autenticación del kit con la aplicación.
 *
 * Igual que {@see ApplicationScaffolder}: idempotente, y si un archivo no encaja con el
 * patrón esperado devuelve `Manual` en lugar de dejarlo a medias.
 */
final class AuthScaffolder
{
    use EditsFiles;

    /**
     * @param  string  $kitPagesPath  Ruta de las páginas del paquete relativa a `resources/js`
     *                                y a `resources/css` (ambas están a la misma profundidad).
     */
    public function __construct(
        private readonly string $basePath,
        private readonly string $kitPagesPath = '../../packages/primevuekit/resources/js/pages',
    ) {}

    /**
     * @return array<string, ScaffoldOutcome>
     */
    public function run(AuthStrategy $strategy): array
    {
        $results = ['app/Models/User.php' => $this->userModel($strategy)];

        // En el modo manual las páginas y las rutas se copian a la app, así que no hay que
        // enseñarle al resolver a mirar dentro del paquete.
        if (! $strategy->publishesToApplication()) {
            $results['resources/js/app.js'] = $this->pageResolver();
            $results['resources/css/app.css'] = $this->tailwindSource();
        }

        // Fortify registra sus propias rutas; el preset del kit y el modo manual no.
        if (! $strategy->usesFortify()) {
            $results['routes/web.php'] = $this->routes($strategy);
        }

        return $results;
    }

    /**
     * Engancha las rutas de autenticación desde `routes/web.php`, como hace Breeze.
     *
     * No se cargan desde el service provider a propósito: una aplicación que sólo quiera
     * los componentes de PrimeVue no debe acabar con un `/login` que no ha pedido.
     */
    public function routes(AuthStrategy $strategy): ScaffoldOutcome
    {
        $original = $this->read('routes/web.php');

        if ($original === null) {
            return ScaffoldOutcome::Manual;
        }

        $kit = '\PrimeVueKit\Auth\AuthRoutes::register();';
        $manual = "require __DIR__.'/auth.php';";

        [$line, $other] = $strategy->publishesToApplication() ? [$manual, $kit] : [$kit, $manual];

        if (str_contains($original, $line)) {
            return ScaffoldOutcome::Skipped;
        }

        // Con las dos líneas presentes las rutas se registrarían dos veces. Es una
        // decisión del usuario cuál quitar, así que se avisa en vez de elegir por él.
        if (str_contains($original, $other)) {
            return ScaffoldOutcome::Manual;
        }

        $content = rtrim($this->normalize($original), "\n")."\n\n".$line."\n";

        return $this->put('routes/web.php', $content, $this->eolOf($original));
    }

    public function userModel(AuthStrategy $strategy): ScaffoldOutcome
    {
        $original = $this->read('app/Models/User.php');

        if ($original === null) {
            return ScaffoldOutcome::Manual;
        }

        $traits = [
            'PrimeVueKit\Auth\Concerns\HasEmailOtp',
            $strategy->usesFortify()
                ? 'Laravel\Fortify\TwoFactorAuthenticatable'
                : 'PrimeVueKit\Auth\Concerns\HasTotp',
        ];

        // Los contratos permiten a los controladores comprobar el modelo con `instanceof`.
        // Con Fortify el segundo factor de aplicación es suyo, así que no se añade SupportsTotp.
        $interfaces = ['Illuminate\Contracts\Auth\MustVerifyEmail', 'PrimeVueKit\Auth\Contracts\SupportsEmailOtp'];

        if (! $strategy->usesFortify()) {
            $interfaces[] = 'PrimeVueKit\Auth\Contracts\SupportsTotp';
        }

        if ($this->declaresAll($original, [...$traits, ...$interfaces])) {
            return ScaffoldOutcome::Skipped;
        }

        $lines = $this->lines($original);

        $classLine = $this->firstMatching($lines, '/^class\s+\w+\s+extends\s+\w+/');
        $traitLine = $this->firstMatching($lines, '/^\s+use\s+[A-Z][A-Za-z0-9_]*\s*[,;]/');
        $lastUse = $this->lastMatching($lines, '/^use\s.+;\s*$/');

        if ($classLine === null || $traitLine === null || $lastUse === null) {
            return ScaffoldOutcome::Manual;
        }

        // Primero las ediciones en el sitio, que no desplazan índices.
        $lines[$classLine] = $this->declareInterfaces($lines[$classLine], $interfaces);

        if (! $this->mergeTraitNames($lines, $traitLine, $traits)) {
            return ScaffoldOutcome::Manual;
        }

        $imports = [];

        // El esqueleto de Laravel deja el contrato de verificación importado pero comentado.
        $commented = $this->firstMatching($lines, '#^//\s*use\s+Illuminate\\\\Contracts\\\\Auth\\\\MustVerifyEmail;#');

        if ($commented !== null) {
            $lines[$commented] = 'use Illuminate\Contracts\Auth\MustVerifyEmail;';
        }

        foreach ([...$traits, ...$interfaces] as $fqcn) {
            $import = 'use '.$fqcn.';';

            if (! str_contains($original, $import) && ! in_array($import, $lines, true)) {
                $imports[] = $import;
            }
        }

        // Un único splice, y al final, para no invalidar los índices calculados arriba.
        array_splice($lines, $lastUse + 1, 0, $imports);

        return $this->putLines('app/Models/User.php', $lines, $this->eolOf($original));
    }

    /**
     * Hace que el resolver de Inertia vea también las páginas que viven en el paquete.
     * Una página con el mismo nombre en la aplicación tiene prioridad.
     */
    public function pageResolver(): ScaffoldOutcome
    {
        $original = $this->read('resources/js/app.js');

        if ($original === null) {
            return ScaffoldOutcome::Manual;
        }

        if (str_contains($original, $this->kitPagesPath)) {
            return ScaffoldOutcome::Skipped;
        }

        $lines = $this->lines($original);
        $resolver = $this->firstMatching($lines, '/^\s*resolve:\s*\(name\)\s*=>/');
        $bootstrap = $this->firstMatching($lines, '/^createInertiaApp\(/');

        if ($resolver === null || $bootstrap === null) {
            return ScaffoldOutcome::Manual;
        }

        $lines[$resolver] = $this->indentOf($lines[$resolver])
            .'resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, pages),';

        array_splice($lines, $bootstrap, 0, $this->lines($this->pageMapBlock()));

        return $this->putLines('resources/js/app.js', $lines, $this->eolOf($original));
    }

    /**
     * Tailwind 4 sólo genera las clases que encuentra escaneando archivos, así que hay
     * que declarar el `resources/js` del paquete completo: no sólo las páginas, también
     * el layout que importan.
     */
    public function tailwindSource(): ScaffoldOutcome
    {
        $original = $this->read('resources/css/app.css');

        if ($original === null) {
            return ScaffoldOutcome::Manual;
        }

        $source = "@source '".dirname($this->kitPagesPath)."/**/*.vue';";

        if (str_contains($original, $source)) {
            return ScaffoldOutcome::Skipped;
        }

        $lines = $this->lines($original);
        $anchor = $this->lastMatching($lines, '/^@source\s/')
            ?? $this->firstMatching($lines, '/^@import\s+[\'"]tailwindcss[\'"];/');

        if ($anchor === null) {
            return ScaffoldOutcome::Manual;
        }

        array_splice($lines, $anchor + 1, 0, [$source]);

        return $this->putLines('resources/css/app.css', $lines, $this->eolOf($original));
    }

    protected function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * @param  list<string>  $names
     */
    private function declaresAll(string $contents, array $names): bool
    {
        foreach ($names as $fqcn) {
            if (! str_contains($contents, 'use '.$fqcn.';')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Añade los contratos al `class ... extends ...`, respetando los que ya hubiera.
     *
     * @param  list<string>  $interfaces
     */
    private function declareInterfaces(string $line, array $interfaces): string
    {
        $names = array_map('class_basename', $interfaces);

        if (preg_match('/^(class\s+\w+\s+extends\s+\w+)\s+implements\s+(.+?)\s*$/', $line, $matches) === 1) {
            $names = array_merge(array_map('trim', explode(',', $matches[2])), $names);
            $line = $matches[1];
        } else {
            $line = rtrim($line);
        }

        $names = array_unique(array_filter($names));
        sort($names);

        return $line.' implements '.implode(', ', $names);
    }

    /**
     * Fusiona los traits del kit en el `use ...;` que ya tiene la clase, ordenados.
     *
     * @param  list<string>  $lines
     * @param  list<string>  $traits
     */
    private function mergeTraitNames(array &$lines, int $index, array $traits): bool
    {
        if (preg_match('/^(\s+)use\s+(.+);\s*$/', $lines[$index], $matches) !== 1) {
            return false;
        }

        $names = array_map('trim', explode(',', $matches[2]));

        foreach ($traits as $fqcn) {
            $names[] = class_basename($fqcn);
        }

        $names = array_unique(array_filter($names));
        sort($names);

        $lines[$index] = $matches[1].'use '.implode(', ', $names).';';

        return true;
    }

    private function pageMapBlock(): string
    {
        $path = $this->kitPagesPath;

        return <<<JS
        // Páginas de PrimeVueKit; una página con el mismo nombre en la aplicación gana.
        const kitPages = Object.fromEntries(
            Object.entries(import.meta.glob('{$path}/**/*.vue')).map(([path, page]) => [
                path.replace('{$path}', './Pages'),
                page,
            ]),
        );

        const pages = { ...kitPages, ...import.meta.glob('./Pages/**/*.vue') };

        JS;
    }
}
