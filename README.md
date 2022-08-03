# Service Container

ServiceContainer is used to simplify the processess of implementing Inversion of Control (IoC) in your code through automatic Dependency Injection (DI).

## How it works
1. Class dependencies are determined by using reflection to examine type hinting in the class constructor.
2. A tree of dependencies is built by recursively performing step 1 on dependencies.
3. Working from the leaf objects to the root, each object is injected with required dependencies and instantiated.
4. Objects, reflections, and dependencies are stored for future use so no repeat calculations need to be performed.

## Usage & Best Practices
+ Define class dependencies by using type hinted arguments in class constructors.
+ Only use ServiceContainer to create objects from classes that will need a single instance (typically services such as logging or database models).
+ Make use of factories with ServiceContainer when multiple object instances of a class are needed.
+ Classes that have additional non-object dependencies need to be created using ServiceContainer individually/manually.
+ Use fully qualified namespaces and a PSR-4 compliant lazy autoloader such as Composer or [bdd88\AutoLoader](https://github.com/bdd88/AutoLoader) to make managing dependencies even easier.

## Quickstart
```
    $serviceContainer = new \Bdd88\ServiceContainer\ServiceContainer();
    $requestedObject = $serviceContainer->create('\Vendor\Namespace\Class', ['optional', 'additional', 'arguments']);
    $previouslyCreatedObject = $serviceContainer->get('\Vendor\Namespace\Class');
```

## Example Scenario
You want to stop using the 'new' keyword within your classes so you can implement unit testing and will have cleaner more modular code. You make use of a database model, file logger, config file loader, and misc classes throughout many classes in your project. Your decide to use Dependency Injection to solve these issues.

#### Before DI:
**customer.php**
```
    class Customer
    {
        public function add(string $customerName)
        {
            $database = new Database();
            $database->addCustomer($customerName);
            $logger = new Logger();
            $logger->save('Customer added');
        }
    }
```

**database.php**
```
    class Database
    {
        public function addCustomer(string $customerName)
        {
            $configFile = new Config();
            $databaseSetting = $configFile->load('databaseSettings');
            // Database Query code here
            $logger = new Logger();
            $logger-save('New customer query ran');
        }
    }
```

**logger.php**
```
    class Logger
    {
        public function save(string $logString)
        {
            $configFile = new Config();
            $loggerSetting = $configFile->load('loggerSettings');
            // Logging code here
        }
    }
```

**config.php**
```
    class Config
    {
        public function load(string $settingsString)
        {
            // Load config code here
        }
    }
```

**index.php**
```
    $newCustomer = new Customer();
    $newCustomer->add('Joe');
```

This results in tightly coupled code that is difficult to test, and that uses extra resources unecessarily by creating multiple instances of functionally identical objects.

#### Using DI:

**customer.php**
```
    class Customer
    {
        private Database $database;
        private Logger $logger;

        public function __construct(Database $database, Logger $logger)
        {
            $this->database = $database;
            $this->logger = $logger;
        }

        public function add(string $customerName)
        {
            $this->database->addCustomer($customerName);
            $this->logger->save('Customer added');
        }
    }
```

**database.php**
```
    class Database
    {
        private Config $config;
        private Logger $logger;

        public function __construct(Config $config, Logger $logger)
        {
            $this->config = $config;
            $this->logger = $logger;
        }

        public function addCustomer(string $customerName)
        {
            $databaseSetting = $this->config->load('databaseSettings');
            // Database Query code here
            $this->logger-save('New customer query ran');
        }
    }
```

**logger.php**
```
    class Logger
    {
        private Config $config;

        public function __construct(Config $config)
        {
            $this->config = $config;
        }

        public function save(string $logString)
        {
            $loggerSetting = $this->config->load('loggerSettings');
            // Logging code here
        }
    }
```

**config.php**
```
    class Config
    {
        public function load(string $settingsString)
        {
            // Load config code here
        }
    }
```

**index.php**
```
    $config = new Config();
    $logger = new Logger($config);
    $database = new Database($config, $logger);
    $newCustomer = new Customer($database, $logger);
    $newCustomer->add('Joe');
```

Your code now has looser coupling and uses less resources as only a single instance of service objects are created, but now you have a new problem:
You now have to manage manual creation of many object dependencies from the top down, which can become more complicated and time consuming as the complexity of your project increases.

#### Using ServiceContainer:

ServiceContainer eliminates one of the biggest downsides of coding with dependency injection by automating the process of manually managing the dependencies from the top down.

**index.php**
```
    $serviceContainer = new ServiceContainer();
    $newCustomer = $serviceContainer->create('Customer');
    $newCustomer->add('Joe');
```

You get the full benefits of dependency injection, without needing to worry about managing the dependencies at the top level. Simply add your dependencies using type hinting within each class constructor, and let ServiceContainer do the rest of the work. ServiceContainer works well even on projects that may have hundreds of classes with interwoven dependencies. 