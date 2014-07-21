<?php namespace Orchestra\Extension;

use RuntimeException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class Finder
{
    /**
     * Filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Application and base path configuration.
     *
     * @var array
     */
    protected $config = array();

    /**
     * List of paths.
     *
     * @var array
     */
    protected $paths = array();

    /**
     * Default manifest options.
     *
     * @var array
     */
    protected $manifestOptions =  array(
        'name'        => null,
        'description' => null,
        'author'      => null,
        'url'         => null,
        'version'     => '>0',
        'config'      => array(),
        'autoload'    => array(),
        'provide'     => array(),
    );

    /**
     * List of reserved name.
     *
     * @var array
     */
    protected $reserved = array(
        'orchestra',
        'resources',
        'orchestra/asset',
        'orchestra/auth',
        'orchestra/debug',
        'orchestra/extension',
        'orchestra/facile',
        'orchestra/foundation',
        'orchestra/html',
        'orchestra/memory',
        'orchestra/model',
        'orchestra/optimize',
        'orchestra/platform',
        'orchestra/resources',
        'orchestra/support',
        'orchestra/testbench',
        'orchestra/view',
        'orchestra/widget',
    );

    /**
     * Construct a new finder.
     *
     * @param  \Illuminate\Filesystem\Filesystem    $files
     * @param  array                                $config
     */
    public function __construct(Filesystem $files, array $config)
    {
        $this->files  = $files;
        $this->config = $config;

        $app  = rtrim($config['path.app'], '/');
        $base = rtrim($config['path.base'], '/');

        // In most cases we would only need to concern with the following
        // path; application folder, vendor folders and workbench folders.
        $this->paths = array(
            "{$app}/",
            "{$base}/vendor/*/*/",
            "{$base}/workbench/*/*/"
        );
    }

    /**
     * Add a new path to finder.
     *
     * @param  string   $path
     * @return Finder
     */
    public function addPath($path)
    {
        if (! in_array($path, $this->paths)) {
            $this->paths[] = $path;
        }

        return $this;
    }

    /**
     * Detect available extensions.
     *
     * @return array
     * @throws \RuntimeException
     */
    public function detect()
    {
        $extensions = array();

        // Loop each path to check if there orchestra.json available within
        // the paths. We would only treat packages that include orchestra.json
        // as an Orchestra Platform extension.
        foreach ($this->paths as $path) {
            $manifests = $this->files->glob("{$path}orchestra.json");

            // glob() method might return false if there an errors, convert
            // the result to an array.
            is_array($manifests) || $manifests = array();

            foreach ($manifests as $manifest) {
                $name = $this->guessExtensionNameFromManifest($manifest, $path);

                if (! is_null($name)) {
                    $extensions[$name] = $this->getManifestContents($manifest);
                }
            }
        }

        return new Collection($extensions);
    }

    /**
     * Get manifest contents.
     *
     * @param  string   $manifest
     * @return array
     * @throws ManifestRuntimeException
     */
    protected function getManifestContents($manifest)
    {
        $path     = $sourcePath = $this->guessExtensionPath($manifest);
        $jsonable = json_decode($this->files->get($manifest), true);

        // If json_decode fail, due to invalid json format. We going to
        // throw an exception so this error can be fixed by the developer
        // instead of allowing the application to run with a buggy config.
        if (is_null($jsonable)) {
            throw new ManifestRuntimeException("Cannot decode file [{$manifest}]");
        }

        isset($jsonable['path']) && $path = $jsonable['path'];

        $paths = array(
            'path'        => rtrim($path, '/'),
            'source-path' => rtrim($sourcePath, '/'),
        );

        // Generate a proper manifest configuration for the extension. This
        // would allow other part of the application to use this configuration
        // to migrate, load service provider as well as preload some
        // configuration.
        return array_merge($paths, $this->generateManifestConfig($jsonable));
    }

    /**
     * Generate a proper manifest configuration for the extension. This
     * would allow other part of the application to use this configuration
     * to migrate, load service provider as well as preload some
     * configuration.
     *
     * @param  array    $jsonable
     * @return array
     */
    protected function generateManifestConfig(array $jsonable)
    {
        $manifest = array();

        // Assign extension manifest option or provide the default value.
        foreach ($this->manifestOptions as $key => $default) {
            $manifest["{$key}"] = array_get($jsonable, $key, $default);
        }

        return $manifest;
    }

    /**
     * Guess extension name from manifest.
     *
     * @param  string   $manifest
     * @param  string   $path
     * @return string
     */
    public function guessExtensionNameFromManifest($manifest, $path)
    {
        list($vendor, $package) = $this->resolveExtensionNamespace($manifest);
        $name = null;

        // Each package should have vendor/package name pattern,
        // except when we deal with app.
        if (rtrim($this->config['path.app'], '/') === rtrim($path, '/')) {
            $name = 'app';
        } elseif (! is_null($vendor) && ! is_null($package)) {
            $name = "{$vendor}/{$package}";
        } else {
            return null;
        }

        if (in_array($name, $this->reserved)) {
            throw new RuntimeException("Unable to register reserved name [{$name}] as extension.");
        }

        return $name;
    }

    /**
     * Guess extension path from manifest file.
     *
     * @param  string   $path
     * @return string
     */
    public function guessExtensionPath($path)
    {
        $path = str_replace('orchestra.json', '', $path);
        $app  = rtrim($this->config['path.app'], '/');
        $base = rtrim($this->config['path.base'], '/');

        return str_replace(
            array("{$app}/", "{$base}/vendor/", "{$base}/workbench/", "{$base}/"),
            array('app::', 'vendor::', 'workbench::', 'base::'),
            $path
        );
    }

    /**
     * Resolve extension namespace name from manifest.
     *
     * @param  string   $manifest
     * @return array
     */
    public function resolveExtensionNamespace($manifest)
    {
        $vendor   = null;
        $package  = null;
        $manifest = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $manifest);
        $fragment = explode(DIRECTORY_SEPARATOR, $manifest);

        // Remove orchestra.json from fragment as we are only interested with
        // the two segment before it.
        array_pop($fragment);

        if (count($fragment) > 2) {
            $package = array_pop($fragment);
            $vendor  = array_pop($fragment);
        }

        return array($vendor, $package);
    }

    /**
     * Resolve extension path.
     *
     * @param  string   $path
     * @return string
     */
    public function resolveExtensionPath($path)
    {
        $app  = rtrim($this->config['path.app'], '/');
        $base = rtrim($this->config['path.base'], '/');

        return str_replace(
            array('app::', 'vendor::', 'workbench::', 'base::'),
            array("{$app}/", "{$base}/vendor/", "{$base}/workbench/", "{$base}/"),
            $path
        );
    }
}
