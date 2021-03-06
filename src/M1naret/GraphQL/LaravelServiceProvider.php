<?php

namespace M1naret\GraphQL;

use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use M1naret\GraphQL\Support\Facades\GraphQL as GraphQLFacade;

class LaravelServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootPublishes();

        $this->bootRouter();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides() : array
    {
        return ['graphql'];
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerGraphQL();
    }

    /**
     * Bootstrap publishes
     *
     * @return void
     */
    private function bootPublishes()
    {
        $configPath = __DIR__ . '/../../config';
        $this->mergeConfigFrom($configPath . '/graphql.php', 'graphql');
    }

    /**
     * Bootstrap router
     *
     * @return void
     */
    private function bootRouter()
    {
        $router = $this->getRouter();
        include __DIR__ . '/routes.php';
    }

    /**
     * Add schemas from config
     *
     * @param GraphQL $graphql
     *
     * @return void
     */
    private function addSchemas(GraphQL $graphql)
    {
        /** @var Repository $config */
        $config = $this->app['config'];

        /** @var array $schemas */
        $schemas = $config->get('graphql.schemas', []);

        foreach ($schemas as $name => $schema) {
            $graphql->addSchema($name, $schema);
        }
    }

    /**
     * Add types from config
     *
     * @param GraphQL $graphql
     *
     * @return void
     */
    private function addTypes(GraphQL $graphql)
    {
        /** @var Repository $config */
        $config = $this->app['config'];

        /** @var array $types */
        $types = $config->get('graphql.types', []);

        foreach ($types as $name => $type) {
            $graphql->addType($type, is_numeric($name) ? null : $name);
        }
    }

    /**
     * Configure security from config
     *
     * @return void
     */
    private function applySecurityRules()
    {
        $maxQueryComplexity = config('graphql.security.query_max_complexity');
        if ($maxQueryComplexity !== null) {
            /** @var QueryComplexity $queryComplexity */
            $queryComplexity = DocumentValidator::getRule('QueryComplexity');
            $queryComplexity->setMaxQueryComplexity($maxQueryComplexity);
        }

        $maxQueryDepth = config('graphql.security.query_max_depth');
        if ($maxQueryDepth !== null) {
            /** @var QueryDepth $queryDepth */
            $queryDepth = DocumentValidator::getRule('QueryDepth');
            $queryDepth->setMaxQueryDepth($maxQueryDepth);
        }

        $disableIntrospection = config('graphql.security.disable_introspection');
        if ($disableIntrospection === true) {
            /** @var DisableIntrospection $disableIntrospection */
            $disableIntrospection = DocumentValidator::getRule('DisableIntrospection');
            $disableIntrospection->setEnabled(DisableIntrospection::ENABLED);
        }
    }

    /**
     * Get the active router.
     *
     * @return Illuminate\Routing\Router|Laravel\Lumen\Routing\Router
     */
    private function getRouter()
    {
        return app('router');
    }

    /**
     * Bootstrap events
     *
     * @param GraphQL $graphql
     *
     * @return void
     */
    private function registerEventListeners(GraphQL $graphql)
    {
        // Update the schema route pattern when schema is added
        $this->app['events']->listen(Events\SchemaAdded::class, function() use ($graphql) {
            $router = $this->getRouter();
            if (method_exists($router, 'pattern')) {
                $schemaNames = array_keys($graphql->getSchemas());
                $router->pattern('graphql_schema', '(' . implode('|', $schemaNames) . ')');
            }
        });
    }

    /**
     * Register facade
     *
     * @return void
     */
    private function registerGraphQL()
    {
        static $registered = false;
        // Check if facades are activated
        if (!$registered && Facade::getFacadeApplication() === $this->app) {
            class_alias(GraphQLFacade::class, 'GraphQL');
            $registered = true;
        }

        $this->app->singleton('graphql', function($app) {
            $graphql = new GraphQL($app);

            $this->addTypes($graphql);

            $this->addSchemas($graphql);

            $this->registerEventListeners($graphql);

            $this->applySecurityRules();

            return $graphql;
        });
    }
}
