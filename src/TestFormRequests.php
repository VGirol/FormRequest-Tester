<?php

namespace VGirol\FormRequestTester;

use \Mockery;
use Closure;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
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
     * @var FormRequest|MockObject
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
        if (\is_array($factory) || ($factory === null)) {
            $mockOptions = $factory ?? [];
            $factory = function (string $formRequestType, array $args) use ($mockOptions): MockObject {
                return \call_user_func_array(
                    [$this, 'getMockForAbstractClass'],
                    \array_merge([$formRequestType, $args], $mockOptions)
                );
            };
        }

        if (!($factory instanceof Closure)) {
            throw new Exception('$factory parameter must be of type Closure, array or null.');
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
            Assert::fail(Messages::NOT_AUTHORIZED);
        }

        if (!empty($this->errors)) {
            Assert::fail(Messages::FAILED);
        }

        $this->succeed(Messages::SUCCEED);
        return $this;
    }

    /**
     * Asserts form request validation have failed.
     *
     * @return $this
     */
    public function assertValidationFailed()
    {
        if (!$this->formRequestAuthorized) {
            Assert::fail(Messages::NOT_AUTHORIZED);
        }

        if (empty($this->errors)) {
            Assert::fail(Messages::SUCCEED);
        }

        $this->succeed(Messages::FAILED);
        return $this;
    }

    /**
     * Asserts the validation errors has the expected keys.
     *
     * @param array $keys
     *
     * @return $this
     */
    public function assertValidationErrors($keys)
    {
        if (!$this->formRequestAuthorized) {
            Assert::fail(Messages::NOT_AUTHORIZED);
        }

        foreach (Arr::wrap($keys) as $key) {
            $this->assertTrue(
                isset($this->errors[$key]),
                \sprintf(Messages::MISSING_ERROR, $key)
            );
        }

        return $this;
    }


    /**
     * Asserts the validation errors doesn't have the given keys.
     *
     * @param array $keys
     *
     * @return $this
     */
    public function assertValidationErrorsMissing($keys)
    {
        if (!$this->formRequestAuthorized) {
            Assert::fail(Messages::NOT_AUTHORIZED);
        }

        foreach (Arr::wrap($keys) as $key) {
            $this->assertTrue(
                !isset($this->errors[$key]),
                \sprintf(Messages::ERROR_NOT_MISSING, $key)
            );
        }

        return $this;
    }

    /**
     * Assert that validation has the expected messages.
     *
     * @param array $messages
     *
     * @return $this
     */
    public function assertValidationMessages($messages)
    {
        if (!$this->formRequestAuthorized) {
            Assert::fail(Messages::NOT_AUTHORIZED);
        }

        $errors = Arr::flatten(Arr::wrap($this->errors));
        foreach ($messages as $message) {
            $this->assertContains(
                $message,
                $errors,
                \sprintf(Messages::MISSING_MESSAGE, $message)
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
        $this->assertTrue($this->formRequestAuthorized, Messages::NOT_AUTHORIZED);

        return $this;
    }

    /**
     * assert that the current user was not authorized by the form request
     *
     * @return $this
     */
    public function assertNotAuthorized()
    {
        $this->assertFalse($this->formRequestAuthorized, Messages::AUTHORIZED);

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
