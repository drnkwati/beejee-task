<?php

namespace Repack\Translation;

use RuntimeException;

class FileLoader implements Loader
{
    /**
     * The default path for the loader.
     *
     * @var string
     */
    protected $path;

    /**
     * All of the registered paths to JSON translation files.
     *
     * @var array
     */
    protected $jsonPaths = array();

    /**
     * All of the namespace hints.
     *
     * @var array
     */
    protected $hints = array();

    /**
     * Create a new file loader instance.
     *
     * @param  string  $path
     * @return void
     */
    public function __construct($files, $path = null)
    {
        $this->path = is_string($files) && is_dir($files) ? $files : $path;
    }

    /**
     * Load the messages for the given locale.
     *
     * @param  string  $locale
     * @param  string  $group
     * @param  string  $namespace
     * @return array
     */
    public function load($locale, $group, $namespace = null)
    {
        if ($group === '*' && $namespace === '*') {
            return $this->loadJsonPaths($locale);
        }

        if (is_null($namespace) || $namespace === '*') {
            return $this->loadPath($this->path, $locale, $group);
        }

        return $this->loadNamespaced($locale, $group, $namespace);
    }

    /**
     * Load a namespaced translation group.
     *
     * @param  string  $locale
     * @param  string  $group
     * @param  string  $namespace
     * @return array
     */
    protected function loadNamespaced($locale, $group, $namespace)
    {
        if (isset($this->hints[$namespace])) {
            $lines = $this->loadPath($this->hints[$namespace], $locale, $group);

            return $this->loadNamespaceOverrides($lines, $locale, $group, $namespace);
        }

        return array();
    }

    /**
     * Load a local namespaced translation group for overrides.
     *
     * @param  array  $lines
     * @param  string  $locale
     * @param  string  $group
     * @param  string  $namespace
     * @return array
     */
    protected function loadNamespaceOverrides(array $lines, $locale, $group, $namespace)
    {
        $file = "{$this->path}/vendor/{$namespace}/{$locale}/{$group}.php";

        if (file_exists($file)) {
            return array_replace_recursive($lines, (require $file));
        }

        return $lines;
    }

    /**
     * Load a locale from a given path.
     *
     * @param  string  $path
     * @param  string  $locale
     * @param  string  $group
     * @return array
     */
    protected function loadPath($path, $locale, $group)
    {
        if (file_exists($full = "{$path}/{$locale}/{$group}.php")) {
            return (require $full);
        }

        return array();
    }

    /**
     * Load a locale from the given JSON file path.
     *
     * @param  string  $locale
     * @return array
     *
     * @throws \RuntimeException
     */
    protected function loadJsonPaths($locale)
    {
        $jsonPaths = array_merge($this->jsonPaths, array($this->path));

        $reducer = function ($items, $callback, $initial = null) {
            return array_reduce($items, $callback, $initial);
        };

        $decoder = function ($output, $path) use ($locale) {
            if (file_exists($full = "{$path}/{$locale}.json")) {
                $decoded = json_decode(file_get_contents($full), true);

                if (is_null($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException("Translation file [{$full}] contains an invalid JSON structure.");
                }

                $output = array_merge($output, $decoded);
            }

            return $output;
        };

        return $reducer($jsonPaths, function ($output, $path) use ($decoder) {
            return $decoder($output, $path);
        }, array());
    }

    /**
     * Add a new namespace to the loader.
     *
     * @param  string  $namespace
     * @param  string  $hint
     * @return void
     */
    public function addNamespace($namespace, $hint)
    {
        $this->hints[$namespace] = $hint;
    }

    /**
     * Add a new JSON path to the loader.
     *
     * @param  string  $path
     * @return void
     */
    public function addJsonPath($path)
    {
        $this->jsonPaths[] = $path;
    }

    /**
     * Get an array of all the registered namespaces.
     *
     * @return array
     */
    public function namespaces()
    {
        return $this->hints;
    }
}
