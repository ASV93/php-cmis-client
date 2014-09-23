<?php
namespace Dkd\PhpCmis\Bindings;

/**
 * This file is part of php-cmis-lib.
 *
 * (c) Sascha Egerer <sascha.egerer@dkd.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Dkd\Enumeration\Exception\InvalidEnumerationValueException;
use Dkd\PhpCmis\AuthenticationProviderInterface;
use Dkd\PhpCmis\Enum\BindingType;
use Dkd\PhpCmis\Exception\CmisRuntimeException;
use Dkd\PhpCmis\SessionParameter;

/**
 * A collection of methods that are used in multiple places within the
 * bindings implementation.
 */
class CmisBindingsHelper
{
    const HTTP_INVOKER_OBJECT = 'dkd.phpcmis.binding.httpinvoker.object';
    const SPI_OBJECT = 'dkd.phpcmis.binding.spi.object';
    const TYPE_DEFINITION_CACHE = 'dkd.phpcmis.binding.typeDefinitionCache';

    /**
     * @param array $parameters
     * @param AuthenticationProviderInterface $authenticationProvider
     * @param \Doctrine\Common\Cache\Cache $typeDefinitionCache
     * @return CmisBindingInterface
     */
    public function createBinding(
        array $parameters,
        AuthenticationProviderInterface $authenticationProvider = null,
        \Doctrine\Common\Cache\Cache $typeDefinitionCache = null
    ) {
        if (count($parameters) === 0) {
            throw new CmisRuntimeException('Session parameters must be set!');
        }

        if (!isset($parameters[SessionParameter::BINDING_TYPE])) {
            throw new CmisRuntimeException('Required binding type is not configured!');
        }

        try {
            $bindingType = BindingType::cast($parameters[SessionParameter::BINDING_TYPE]);

            $bindingFactory = $this->getCmisBindingFactory();

            switch (true) {
                case $bindingType->equals(BindingType::BROWSER):
                    $binding = $bindingFactory->createCmisBrowserBinding(
                        $parameters,
                        $authenticationProvider,
                        $typeDefinitionCache
                    );
                    break;
                case $bindingType->equals(BindingType::ATOMPUB):
                case $bindingType->equals(BindingType::WEBSERVICES):
                case $bindingType->equals(BindingType::CUSTOM):
                default:
                    $binding = null;
            }

            if (!is_object($binding) || !($binding instanceof CmisBinding)) {
                throw new CmisRuntimeException(
                    sprintf(
                        'The given binding "%s" is not yet implemented.',
                        $parameters[SessionParameter::BINDING_TYPE]
                    )
                );
            }

        } catch (InvalidEnumerationValueException $exception) {
            throw new CmisRuntimeException(
                'Invalid binding type given: ' . $parameters[SessionParameter::BINDING_TYPE]
            );
        }

        return $binding;
    }

    /**
     * Gets the SPI object for the given session. If there is already a SPI
     * object in the session it will be returned. If there is no SPI object it
     * will be created and put into the session.
     *
     * @param BindingSessionInterface $session
     * @return CmisInterface
     */
    public function getSpi(BindingSessionInterface $session)
    {
        $spi = $session->get(self::SPI_OBJECT);

        if ($spi !== null) {
            return $spi;
        }

        $spiClass = $session->get(SessionParameter::BINDING_CLASS);
        if (empty($spiClass) || !class_exists($spiClass)) {
            throw new CmisRuntimeException(
                sprintf('The given binding class "%s" is not valid!', $spiClass)
            );
        }

        if (!is_a($spiClass, '\\Dkd\\PhpCmis\\Bindings\\CmisInterface', true)) {
            throw new CmisRuntimeException(
                sprintf('The given binding class "%s" does not implement required CmisInterface!', $spiClass)
            );
        }

        try {
            $spi = new $spiClass($session);
        } catch (\Exception $exception) {
            throw new CmisRuntimeException(
                sprintf('Could not create object of type "%s"!', $spiClass),
                null,
                $exception
            );
        }

        $session->put(self::SPI_OBJECT, $spi);

        return $spi;
    }

    /**
     * @param BindingSessionInterface $session
     * @return mixed
     * @throws CmisRuntimeException
     */
    public function getHttpInvoker(BindingSessionInterface $session)
    {
        $invoker = $session->get(self::HTTP_INVOKER_OBJECT);

        if ($invoker !== null) {
            return $invoker;
        }

        $invokerClass = $session->get(SessionParameter::HTTP_INVOKER_CLASS);
        if (!is_a($invokerClass, '\\GuzzleHttp\\ClientInterface', true)) {
            throw new CmisRuntimeException(
                sprintf('The given HTTP Invoker class "%s" is not valid!', $invokerClass)
            );
        }

        try {
            $invoker = new $invokerClass;
        } catch (\Exception $exception) {
            throw new CmisRuntimeException(
                sprintf('Could not create object of type "%s"!', $invokerClass),
                null,
                $exception
            );
        }

        $session->put(self::HTTP_INVOKER_OBJECT, $invoker);

        return $invoker;
    }

    public function getJsonConverter(BindingSessionInterface $session)
    {
        $jsonConverter = $session->get(SessionParameter::JSON_CONVERTER);

        if ($jsonConverter !== null) {
            return $jsonConverter;
        }

        $jsonConverterClass = $session->get(SessionParameter::JSON_CONVERTER_CLASS);
        if (empty($jsonConverterClass) || !class_exists($jsonConverterClass)) {
            throw new CmisRuntimeException(
                sprintf('The given JSON Converter class "%s" is not valid!', $jsonConverterClass)
            );
        }

        try {
            $jsonConverter = new $jsonConverterClass();
        } catch (\Exception $exception) {
            throw new CmisRuntimeException(
                sprintf('Could not create object of type "%s"!', $jsonConverterClass),
                null,
                $exception
            );
        }

        // we have a json converter object -> put it into the session
        $session->put(SessionParameter::JSON_CONVERTER, $jsonConverter);

        return $jsonConverter;
    }

    /**
     * @return CmisBindingFactory
     */
    protected function getCmisBindingFactory()
    {
        return new CmisBindingFactory();
    }
}