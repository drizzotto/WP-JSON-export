<?php
namespace Posts_Jsoner\autoload;

class Autoloader
{
    private array $prefixes;


    public function __construct(string $plguin_dir)
    {
        foreach (Psr4Autoload::getNamespaces($plguin_dir) as $prefix=>$class) {
            $this->addNamespace($prefix, $class);
        }

        spl_autoload_register(function (string $class) : string {
            return $this->loadClass($class);
        });
    }

    public function addNamespace(string $prefix, string $base_dir, bool $prepend = false): void
    {
        // normalize namespace prefix
        $prefix = trim($prefix, '\\') . '\\';

        // normalize the base directory with a trailing separator
        $base_dir = rtrim($base_dir, DIRECTORY_SEPARATOR) . '/';

        // initialize the namespace prefix array
        if (!isset($this->prefixes[$prefix])) {
            $this->prefixes[$prefix] = array();
        }

        // retain the base directory for the namespace prefix
        if ($prepend) {
            array_unshift($this->prefixes[$prefix], $base_dir);
        } else {
            $this->prefixes[$prefix][] = $base_dir;
        }
    }

    public function loadClass(string $class): string
    {
        // the current namespace prefix
        $prefix = $class;

        // work backwards through the namespace names of the fully-qualified
        // class name to find a mapped file name
        while (false !== $pos = strrpos($prefix, '\\')) {

            // retain the trailing namespace separator in the prefix
            $prefix = substr($class, 0, $pos + 1);

            // the rest is the relative class name
            $relative_class = substr($class, $pos + 1);

            // try to load a mapped file for the prefix and relative class
            $mapped_file = $this->loadMappedFile($prefix, $relative_class);
            if ($mapped_file) {
                return $mapped_file;
            }

            // remove the trailing namespace separator for the next iteration
            // of strrpos()
            $prefix = rtrim($prefix, '\\');
        }

        // never found a mapped file
        return false;
    }

    /**
     * @param string $prefix
     * @param string $relative_class
     * @return string
     */
    protected function loadMappedFile(string $prefix, string $relative_class): string
    {
        // are there any base directories for this namespace prefix?
        if (!isset($this->prefixes[$prefix])) {
            return false;
        }

        // look through base directories for this namespace prefix
        foreach ($this->prefixes[$prefix] as $base_dir) {

            // replace the namespace prefix with the base directory,
            // replace namespace separators with directory separators
            // in the relative class name, append with .php
            $file = $base_dir
                . str_replace('\\', '/', $relative_class)
                . '.php';

            // if the mapped file exists, require it
            if ($this->requireFile($file)) {
                // yes, we're done
                return $file;
            }
        }

        // never found it
        return false;
    }

    /**
     * @param string $file
     * @return bool
     */
    protected function requireFile(string $file): bool
    {
        if (file_exists($file)) {
            require_once $file;
            return true;
        }

        return false;
    }
}