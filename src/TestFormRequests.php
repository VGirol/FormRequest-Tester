<?php

namespace VGirol\FormRequestTester;

use \Mockery;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * This trait provides some tools to test FormRequest (as concrete or abstract class).
 */
trait TestFormRequests
{
    abstract public static function assertTrue($condition, string $message = ''): void;
    abstract public static function assertFalse($condition, string $message = ''): void;
    abstract public static function assertContains($needle, iterable $haystack, string $message = ''): void;

    /**
     * The current form request that needs to be tested
     *
     * @var \Illuminate\Foundation\Http\FormRequest
     */
    private $currentFormRequest;

    /**
     * Validation errors
     * null when validation haven't been done yet
     *
     * @var array
     */
    private $errors = null;

    /**
     * The form requests authorization status
     *
     * @var boolean
     */
    private $formRequestAuthorized = true;

    /**
     * Create new instance of form request with
     *
     * @param string $formRequestType
     * @param array  $data            default to empty
     * @param array  $options         ['route' => 'Route to instantiate form request with',
     *                                'method' => 'to instantiate form request with']
     *
     * @return \Illuminate\Foundation\Http\FormRequest
     */
    public function createFormRequest(string $formRequestType, $data = [], $options = [])
    {
        $options = \array_merge(
            [
                'method' => 'POST',
                'route' => '/fake-route'
            ],
            $options
        );

        $currentFormRequest = $formRequestType::create($options['route'], $options['method'], $data)
            ->setContainer($this->app)
            ->setRedirector($this->makeRequestRedirector());

        $currentFormRequest->setRouteResolver(
            /**
             * @return Route
             */
            function () use ($currentFormRequest) {
                $routes = Route::getRoutes();
                try {
                    $route = $routes->match($currentFormRequest);
                } catch (\Exception $e) {
                    $route = null;
                } finally {
                    return $route;
                }
            }
        );

        return $currentFormRequest;
    }

    /**
     * Create a mock instance of form request.
     *
     * @param string             $formRequestType
     * @param array              $data            default to empty
     * @param array              $options         ['route' => 'Route to instantiate form request with',
     *                                            'method' => 'to instantiate form request with']
     * @param Closure|array|null $factory
     * The factory used to create the FormRequest mock depends of the value of the $factory parameter :
     * - if null, the "getMockForAbstractClass" method of PHPUnit library is used with default parameters
     * - if $factory is an array, the "getMockForAbstractClass" method of PHPUnit library is used with the parameters
     *   provided in the $factory array. This array must contain the six last parameters of the
     *   "getMockForAbstractClass" method (i.e. $mockClassName, $callOriginalConstructor, $callOriginalClone,
     *   $callAutoload, $mockedMethods, $cloneArguments).
     * - if $factory is a Closure, this Closure will be used to create the FormRequest mock. This Closure must be of
     *   the form :
     *     function (string $formRequestType, array $args): MockObject
     *
     * @return MockObject
     */
    public function createFormRequestMock(string $formRequestType, $data = [], $options = [], $factory = null)
    {
        if ($factory === null) {
            $factory = [];
        }

        if (\is_array($factory)) {
            $mockOptions = $factory;
            $factory = function (string $formRequestType, array $args) use ($mockOptions) {
                return \call_user_func_array(
                    [$this, 'getMockForAbstractClass'],
                    \array_merge([$formRequestType, $args], $mockOptions)
                );
            };
        }

        $this->setFormRequestFactory($formRequestType, $factory);

        return $this->createFormRequest($formRequestType, $data, $options);
    }

    /**
     * Create and validate form request.
     *
     * @param string $formRequestType
     * @param array  $data            default to empty
     * @param array  $options         ['route' => 'Route to instantiate form request with',
     *                                'method' => 'to instantiate form request with']
     *
     * @return static
     */
    public function formRequest(string $formRequestType, $data = [], $options = [])
    {
        $this->currentFormRequest = $this->createFormRequest($formRequestType, $data, $options);
        $this->validateFormRequest();

        return $this;
    }

    /**
     * Create and validate mock for form request.
     *
     * @param string             $formRequestType
     * @param array              $data            default to empty
     * @param array              $options         ['route' => 'Route to instantiate form request with',
     *                                            'method' => 'to instantiate form request with']
     * @param Closure|array|null $factory
     *
     * @return static
     */
    public function mockFormRequest(string $formRequestType, $data = [], $options = [], $factory = null)
    {
        $this->currentFormRequest = $this->createFormRequestMock($formRequestType, $data, $options, $factory);
        $this->validateFormRequest();

        return $this;
    }

    /**
     * Undocumented function
     *
     * @param string $formRequestType
     *
     * @return void
     */
    public function setFormRequestFactory(string $formRequestType, Closure $factory)
    {
        $formRequestType::setFactory(
            function (
                array $query = [],
                array $request = [],
                array $attributes = [],
                array $cookies = [],
                array $files = [],
                array $server = [],
                $content = null
            ) use (
                $formRequestType,
                $factory
            ) {
                $formRequestType::setFactory(null);

                return \call_user_func_array(
                    $factory,
                    [
                        $formRequestType,
                        [
                            $query, $request, $attributes, $cookies, $files, $server, $content
                        ]
                    ]
                );
            }
        );
    }

    /**
     * create fake request redirector to be used in request
     *
     * @return \Illuminate\Routing\Redirector
     */
    private function makeRequestRedirector()
    {
        $fakeUrlGenerator = Mockery::mock();
        $fakeUrlGenerator->shouldReceive('to', 'route', 'action', 'previous')->withAnyArgs()->andReturn(null);

        $redirector = Mockery::mock(Redirector::class);
        $redirector->shouldReceive('getUrlGenerator')->andReturn($fakeUrlGenerator);

        return $redirector;
    }

    /**
     * validates form request and save the errors
     *
     * @return void
     */
    private function validateFormRequest()
    {
        try {
            $this->currentFormRequest->validateResolved();
        } catch (ValidationException $e) {
            $this->errors = $e->errors();
        } catch (AuthorizationException $e) {
            $this->formRequestAuthorized = false;
        }
    }

    /*----------------------------------------------------
     * Assertions functions
    --------------------------------------------------- */
    /**
     * assert form request validation have passed
     *
     * @return $this
     */
    public function assertValidationPassed()
    {
        if (!$this->formRequestAuthorized) {
            Assert::fail('Form Request is not authorized');
        }

        if (!empty($this->errors)) {
            Assert::fail('Validation have failed');
        }

        $this->succeed('Validation passed successfully');
        return $this;
    }

    /**
     * assert form request validation have failed
     *
     * @return $this
     */
    public function assertValidationFailed()
    {
        if (!$this->formRequestAuthorized) {
            Assert::fail('Form Request is not authorized');
        }

        if (empty($this->errors)) {
            Assert::fail('Validation have passed');
        }

        $this->succeed('Validation have failed');
        return $this;
    }

    /**
     * assert the validation errors has the following keys
     *
     * @param array $keys
     * @return $this
     */
    public function assertValidationErrors($keys)
    {
        if (!$this->formRequestAuthorized) {
            Assert::fail('Form Request is not authorized');
        }

        foreach (Arr::wrap($keys) as $key) {
            $this->assertTrue(
                isset($this->errors[$key]),
                "Failed to find a validation error for key: '{$key}'"
            );
        }

        return $this;
    }


    /**
     * assert the validation errors doesn't have a key
     *
     * @param array $keys
     * @return $this
     */
    public function assertValidationErrorsMissing($keys)
    {
        if (!$this->formRequestAuthorized) {
            Assert::fail('Form Request is not authorized');
        }

        foreach (Arr::wrap($keys) as $key) {
            $this->assertTrue(
                !isset($this->errors[$key]),
                "validation error for key: '{$key}' was found in errors array"
            );
        }

        return $this;
    }

    /**
     * assert that validation has the messages
     *
     * @return $this
     */
    public function assertValidationMessages($messages)
    {
        $errors = Arr::flatten(Arr::wrap($this->errors));
        foreach ($messages as $message) {
            $this->assertContains(
                $message,
                $errors,
                "Failed to find the validation message '${message}' in the validation messages"
            );
        }

        return $this;
    }

    /**
     * assert that the current user was authorized by the form request
     *
     * @return $this
     */
    public function assertAuthorized()
    {
        $this->assertTrue($this->formRequestAuthorized, "Form Request is not authorized");

        return $this;
    }

    /**
     * assert that the current user was not authorized by the form request
     *
     * @return $this
     */
    public function assertNotAuthorized()
    {
        $this->assertFalse($this->formRequestAuthorized, "Form Request is authorized");

        return $this;
    }

    /**
     * assert the success of the current test
     *
     * @param string $message
     * @return void
     */
    private function succeed($message = '')
    {
        $this->assertTrue(true, $message);
    }
}
