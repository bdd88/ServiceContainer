<?php
namespace Bdd88\ServiceContainer;

use ReflectionClass;

/**
 * An automated recursive dependency injection container.
 * 
 * @version 1.1.0
 * @link https://github.com/bdd88/ServiceContainer
 */
class ServiceContainer
{
    private array $classReflections;
    private array $dependencyMaps;
    private array $objects;

    /** Ensure consistency for class namespaces (since reflection doesn't use a leading slash). */
    private function validateNamespace(string $className): string
    {
        if ($className[0] === '\\') {
            $className = substr($className, 1);
        }
        return $className;
    }

    /** Retrieve or create an instantiable class reflection. */
    private function getReflection(string $className): ReflectionClass|NULL
    {
        // Retrieve the reflection if already in the map.
        if (isset($this->classReflections[$className])) {
            return $this->classReflections[$className];
        }

        // Attempt to create the reflection and store it in the map.
        if (class_exists($className) === FALSE) {
            return NULL;
        }
        $classReflection = new ReflectionClass($className);
        if ($classReflection->isInstantiable() === FALSE) {
            return NULL;
        }
        $this->classReflections[$className] = $classReflection;
        return $classReflection;
    }

    /** Use type hinting in a class constructor to list the direct object dependencies. */
    private function listClassDependencies(string $className): array|NULL
    {
        // Retrieve the dependency map if it has already been calculated previously.
        if (isset($this->dependencyMaps[$className])) {
            return $this->dependencyMaps[$className];
        }

        // Get the class reflection.
        $classReflection = $this->getReflection($className);
        if ($classReflection === NULL) {
            return NULL;
        }

        // Check to see if dependencies exist.
        $classConstructor = $classReflection->getConstructor();
        if ($classConstructor === NULL) {
            return NULL;
        }
        if ($classConstructor->getNumberOfParameters() === 0) {
            return NULL;
        }

        // Use reflection to examine constructor type hinting. Store the class dependencies in the mapping.
        $dependencies = array();
        foreach ($classConstructor->getParameters() as $parameter) {
            $dependencyName = $parameter->getType()->getName();
            if (class_exists($dependencyName)) {
                $dependencies[] = $dependencyName;
            }
        }
        $this->dependencyMaps[$className] = $dependencies;
        return $dependencies;
    }

    /** Recursively create the tree of class dependencies for the requested object class. */
    private function createDependencyTree(string $className): array
    {
        $dependencyTree[] = $className;
        $branch = $this->listClassDependencies($className);
        if ($branch !== NULL) {
            foreach ($branch as $leaf) {
                $dependencyTree = array_merge($dependencyTree, $this->createDependencyTree($leaf));
            }
        }
        return $dependencyTree;
    }

    /**
     * Create, store, and return an object of the requested class.
     *
     * @param string $className The class name including namespace of the object to be created.
     * @param array|null $parameters (optional) Additional non-object arguments.
     * @return object|FALSE Returns the requested object on success, or FALSE if the object couldn't be created.
     */
    public function create(string $className, ?array $parameters = NULL): object|FALSE
    {
        // Check the class can be instantiated.
        $className = $this->validateNamespace($className);
        if ($this->getReflection($className) === NULL) {
            return FALSE;
        }

        // Create, store, and inject each object in the tree from leaf to root.
        $dependencyTree = $this->createDependencyTree($className);
        foreach (array_reverse($dependencyTree) as $dependencyName) {

            // Immediately return the requested object if it has already been intantiated.
            if (isset($this->objects[$dependencyName])) {
                continue;
            }

            // Build the constructor arguments array from stored objects.
            $arguments = array();
            if (isset($this->dependencyMaps[$dependencyName])) {
                foreach ($this->dependencyMaps[$dependencyName] as $injection) {
                    $arguments[] = $this->objects[$injection];
                }
            }

            // Add user supplied arguments to the arguments array.
            if ($dependencyName === $className && isset($parameters)) {
                $arguments = array_merge($arguments, $parameters);
            }

            // Inject depedendencies and store the newly instantiated object.
            $classReflection = $this->getReflection($dependencyName);
            $this->objects[$dependencyName] = $classReflection->newInstanceArgs($arguments);
        }

        return $this->objects[$className];
    }

    /**
     * Retrieve a previously created object.
     *
     * @param string $className The class name including namespace of the object to be created.
     * @return object|FALSE Returns the requested object on success, or FALSE if the object hasn't been created yet.
     */
    public function get(string $className): object|FALSE
    {
        $className = $this->validateNamespace($className);
        if (isset($this->objects[$className])) {
            return $this->objects[$className];
        }
        return FALSE;
    }

}



?>
