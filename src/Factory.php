<?php

namespace Orchestra\Extension;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Orchestra\Memory\Memorizable;
use Illuminate\Support\Collection;
use Orchestra\Extension\Traits\Operation;
use Orchestra\Extension\Traits\Dispatchable;
use Illuminate\Contracts\Container\Container;
use Orchestra\Extension\Bootstrap\LoadExtension;
use Orchestra\Contracts\Extension\Factory as FactoryContract;
use Orchestra\Contracts\Extension\Dispatcher as DispatcherContract;
use Orchestra\Contracts\Extension\StatusChecker as StatusCheckerContract;

class Factory implements FactoryContract
{
    use Dispatchable, Memorizable, Operation;

    /**
     * Application instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $app;

    /**
     * The event dispatcher implementation.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * Dispatcher instance.
     *
     * @var \Orchestra\Contracts\Extension\Dispatcher
     */
    protected $dispatcher;

    /**
     * List of extensions.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $extensions;

    /**
     * List of routes.
     *
     * @var array
     */
    protected $routes = [];

    /**
     * Construct a new Application instance.
     *
     * @param  \Illuminate\Contracts\Container\Container  $app
     * @param  \Orchestra\Contracts\Extension\Dispatcher  $dispatcher
     * @param  \Orchestra\Contracts\Extension\StatusChecker  $status
     */
    public function __construct(
        Container $app,
        DispatcherContract $dispatcher,
        StatusCheckerContract $status
    ) {
        $this->app = $app;
        $this->events = $this->app->make('events');
        $this->dispatcher = $dispatcher;
        $this->extensions = new Collection();
        $this->status = $status;
    }

    /**
     * Detect all extensions.
     *
     * @return \Illuminate\Support\Collection|array
     */
    public function detect()
    {
        $this->events->fire('orchestra.extension: detecting');

        $extensions = $this->finder()->detect();

        $collection = $extensions->map(function ($item) {
            return Arr::except($item, ['description', 'author', 'url', 'version']);
        });

        $this->memory->put('extensions.available', $collection->all());

        return $extensions;
    }

    /**
     * Get extension finder.
     *
     * @return \Orchestra\Contracts\Extension\Finder
     */
    public function finder()
    {
        return $this->app->make('orchestra.extension.finder');
    }

    /**
     * Get an option for a given extension.
     *
     * @param  string  $name
     * @param  string  $option
     * @param  mixed   $default
     *
     * @return mixed
     */
    public function option($name, $option, $default = null)
    {
        if (! isset($this->extensions[$name])) {
            return value($default);
        }

        return Arr::get($this->extensions[$name], $option, $default);
    }

    /**
     * Check whether an extension has a writable public asset.
     *
     * @param  string  $name
     *
     * @return bool
     */
    public function permission($name)
    {
        $finder = $this->finder();
        $memory = $this->memory;
        $basePath = rtrim($memory->get("extensions.available.{$name}.path", $name), '/');
        $path = $finder->resolveExtensionPath("{$basePath}/public");

        return $this->isWritableWithAsset($name, $path);
    }

    /**
     * Publish an extension.
     *
     * @param  string
     *
     * @return void
     */
    public function publish($name)
    {
        $this->app->make('orchestra.publisher.migrate')->extension($name);
        $this->app->make('orchestra.publisher.asset')->extension($name);

        $this->events->fire('orchestra.publishing', [$name]);
        $this->events->fire("orchestra.publishing: {$name}");
    }

    /**
     * Register an extension.
     *
     * @param  string  $name
     * @param  string  $path
     *
     * @return bool
     */
    public function register($name, $path)
    {
        return $this->finder()->registerExtension($name, $path);
    }

    /**
     * Get extension route handle.
     *
     * @param  string   $name
     * @param  string   $default
     *
     * @return \Orchestra\Contracts\Extension\UrlGenerator
     */
    public function route($name, $default = '/')
    {
        // Boot the extension.
        ! $this->booted() && $this->app->make(LoadExtension::class)->bootstrap($this->app);

        if (! isset($this->routes[$name])) {

            // All route should be manage via `orchestra/extension::handles.{name}`
            // config key, except for orchestra/foundation.
            $key = "orchestra/extension::handles.{$name}";

            $prefix = $this->app->make('config')->get($key, $default);

            $this->routes[$name] = $this->app->make('orchestra.extension.url')->handle($prefix);
        }

        return $this->routes[$name];
    }

    /**
     * Check whether an extension has a writable public asset.
     *
     * @param  string  $name
     * @param  string  $path
     *
     * @return bool
     */
    protected function isWritableWithAsset($name, $path)
    {
        $files = $this->app->make('files');
        $publicPath = $this->app['path.public'];
        $targetPath = "{$publicPath}/packages/{$name}";

        if (Str::contains($name, '/') && ! $files->isDirectory($targetPath)) {
            list($vendor) = explode('/', $name);
            $targetPath = "{$publicPath}/packages/{$vendor}";
        }

        $isWritable = $files->isWritable($targetPath);

        if ($files->isDirectory($path) && ! $isWritable) {
            return false;
        }

        return true;
    }
}
