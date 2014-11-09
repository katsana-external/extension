<?php namespace Orchestra\Extension;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Config\Repository as Config;
use Orchestra\Contracts\Extension\Finder as FinderContract;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Orchestra\Contracts\Extension\Dispatcher as DispatcherContract;

class Dispatcher implements DispatcherContract
{
    /**
     * Config Repository instance.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * Filesystem instance.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $dispatcher;

    /**
     * Filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Finder instance.
     *
     * @var \Orchestra\Contracts\Extension\Finder
     */
    protected $finder;

    /**
     * Provider instance.
     *
     * @var \Orchestra\Extension\ProviderRepository
     */
    protected $provider;

    /**
     * List of extensions to be boot.
     *
     * @var array
     */
    protected $extensions = [];

    /**
     * Construct a new Application instance.
     *
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  \Orchestra\Contracts\Extension\Finder  $finder
     * @param  \Orchestra\Extension\ProviderRepository  $provider
     */
    public function __construct(
        Config $config,
        EventDispatcher $dispatcher,
        Filesystem $files,
        FinderContract $finder,
        ProviderRepository $provider
    ) {
        $this->config     = $config;
        $this->dispatcher = $dispatcher;
        $this->files      = $files;
        $this->finder     = $finder;
        $this->provider   = $provider;
    }

    /**
     * Register the extension.
     *
     * @param  string   $name
     * @param  array    $options
     * @return void
     */
    public function register($name, array $options)
    {
        $handles = Arr::get($options, 'config.handles');

        // Set the handles to orchestra/extension package config (if available).
        if (! is_null($handles)) {
            $this->config->set("orchestra/extension::handles.{$name}", $handles);
        }

        // Get available service providers from orchestra.json and register
        // it to Laravel. In this case all service provider would be eager
        // loaded since the application would require it from any action.
        $services = Arr::get($options, 'provide', []);
        ! empty($services) && $this->provider->provides($services);

        // Register the extension so we can boot it later, this action is
        // to allow all service providers to be registered first before we
        // start the extension. An extension might be using another extension
        // to work.
        $this->extensions[$name] = $options;
        $this->start($name, $options);
    }

    /**
     * Boot all extensions.
     *
     * @return void
     */
    public function boot()
    {
        foreach ($this->extensions as $name => $options) {
            $this->fireEvent($name, $options, 'booted');
        }
    }

    /**
     * Start the extension.
     *
     * @param  string   $name
     * @param  array    $options
     * @return void
     */
    public function start($name, array $options)
    {
        $file   = $this->files;
        $finder = $this->finder;
        $base   = rtrim($options['path'], '/');
        $source = rtrim(Arr::get($options, 'source-path', $base), '/');

        // By now, extension should already exist as an extension. We should
        // be able start orchestra.php start file on each package.
        foreach ($this->getExtensionPaths($base, $options) as $path) {
            $path = str_replace(
                ['source-path::', 'app::/'],
                ["{$source}/", 'app::'],
                $path
            );

            $path = $finder->resolveExtensionPath($path);

            if ($file->isFile($path)) {
                $file->getRequire($path);
            }
        }

        $this->fireEvent($name, $options, 'started');
    }

    /**
     * Shutdown an extension.
     *
     * @param  string   $name
     * @param  array    $options
     * @return void
     */
    public function finish($name, array $options)
    {
        $this->fireEvent($name, $options, 'done');
    }

    /**
     * Fire events.
     *
     * @param  string   $name
     * @param  array    $options
     * @param  string   $type
     * @return void
     */
    protected function fireEvent($name, $options, $type = 'started')
    {
        $this->dispatcher->fire("extension.{$type}", [$name, $options]);
        $this->dispatcher->fire("extension.{$type}: {$name}", [$options]);
    }

    /**
     * Get list of available paths for the extension.
     *
     * @param  string   $base
     * @param  array    $options
     * @return array
     */
    protected function getExtensionPaths($base, array $options)
    {
        $autoload = Arr::get($options, 'autoload', []);

        $resolver = function ($path) use ($base) {
            if (Str::contains($path, '::')) {
                return $path;
            }

            return "source-path::" . ltrim($path, '/');
        };

        $paths = array_map($resolver, $autoload);

        return array_merge(
            $paths,
            ["source-path::src/orchestra.php", "source-path::orchestra.php"]
        );
    }
}
