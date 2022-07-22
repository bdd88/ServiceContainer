<?php
namespace ServiceContainer;

use ReflectionClass;

/**
 * An automatic (recursive) dependency injection container.
 * 
 * @version 1.0.0
 * @link https://github.com/bdd88/ServiceContainer.git
 */
class ServiceContainer
{
    private array $objects = array();
    private array $aliases = array();

    public function __construct()
    {
        $this->loadAliasesConfig();
    }

    /** Parse an ini file for mapping/aliasing abstract classes to concrete classes. */
    public function loadAliasesConfig(?string $aliasConfigPath = NULL): void
    {
        $aliasConfigPath = $aliasConfigPath ?? dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'aliases.default.ini';
        $this->aliases = parse_ini_file($aliasConfigPath, TRUE, INI_SCANNER_TYPED);
    }

    /** Checks if a namespace string is properly formatted, and will correct it if it isn't. */
    public function validateNamespace(string $namespaceString): string
    {
        $namespaceArray = explode('\\', $namespaceString);
        if ($namespaceArray[0] !== '') {
            array_unshift($namespaceArray, '');
        }
        if (end($namespaceArray) === '') {
            array_shift($namespaceArray);
        }
        return implode('\\', $namespaceArray);
    }

    /** Create a list of class dependencies for a specified class. */
    private function listDependencies(ReflectionClass $class): array
    {
        $classDependencies = array();
        $constructor = $class->getConstructor();
        if (isset($constructor)) {
            if ($constructor->getNumberOfParameters() > 0) {
                foreach ($constructor->getParameters() as $parameter) {
                    $dependencyName = (string) $parameter->getType();
                    $dependencyName = $this->validateNamespace($dependencyName);
                    if (class_exists($dependencyName)) {
                        $classDependencies[] = $dependencyName;
                    }
                }
            }
        }
        return $classDependencies;
    }

    /**
     * Recursively create/retrieve object dependencies.
     *
     * @param string $className Must have the fully qualified namespace included. IE \FreshFrame\Model\Logger
     * @param array|null $parameters Additional construction parameters.
     * @return object|null The object instance of the requested class.
     */
    public function create(string $className, ?array $parameters = NULL): object|NULL
    {
        $className = $this->validateNamespace($className);
        $class = new ReflectionClass($className);
        if ($class->isInstantiable()) {
            $dependencyObjects = array();
            $dependencyNames = $this->listDependencies($class);
            if ($dependencyNames > 0) {
                foreach ($dependencyNames as $dependencyName) {
                    if (array_key_exists($dependencyName, $this->aliases)) {
                        $dependencyName = $this->aliases[$dependencyName];
                    }
                    if (!array_key_exists($dependencyName, $this->objects)) {
                        $this->create($dependencyName);
                    }
                    $dependencyObjects[] = $this->objects[$dependencyName];
                }
            }

            // Instantiate the requested object by injecting dependencies, store it for future use, and return it.
            $instanceArgs = (isset($parameters)) ? array_merge($dependencyObjects, $parameters) : $dependencyObjects;
            $object = $class->newInstanceArgs($instanceArgs);
            $this->objects[$className] = $object;
            return $object;
        }
    }

    /** Retrieve a previously instantiated object and return it. */
    public function get(string $className): object
    {
        return $this->objects[$className];
    }

}

?>
