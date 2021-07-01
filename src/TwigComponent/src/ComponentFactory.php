<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\TwigComponent;

use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @experimental
 */
final class ComponentFactory
{
    private ServiceLocator $components;
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(ServiceLocator $components, PropertyAccessorInterface $propertyAccessor)
    {
        $this->components = $components;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * Creates the component and "mounts" it with the passed data.
     */
    public function create(string $name, array $data = []): object
    {
        $component = $this->getComponent($name);

        $this->mount($component, $data);

        // set data that wasn't set in mount on the component directly
        foreach ($data as $property => $value) {
            if (!$this->propertyAccessor->isWritable($component, $property)) {
                throw new \LogicException(sprintf('Unable to write "%s" to component "%s". Make sure this is a writable property or create a mount() with a $%s argument.', $property, \get_class($component), $property));
            }

            $this->propertyAccessor->setValue($component, $property, $value);
        }

        return $component;
    }

    /**
     * Returns the "unmounted" component.
     */
    public function get(string $name): object
    {
        return $this->getComponent($name);
    }

    private function mount(object $component, array &$data): void
    {
        try {
            $method = (new \ReflectionClass($component))->getMethod('mount');
        } catch (\ReflectionException $e) {
            // no hydrate method
            return;
        }

        $parameters = [];

        foreach ($method->getParameters() as $refParameter) {
            $name = $refParameter->getName();

            if (\array_key_exists($name, $data)) {
                $parameters[] = $data[$name];

                // remove the data element so it isn't used to set the property directly.
                unset($data[$name]);
            } elseif ($refParameter->isDefaultValueAvailable()) {
                $parameters[] = $refParameter->getDefaultValue();
            } else {
                throw new \LogicException(sprintf('%s::mount() has a required $%s parameter. Make sure this is passed or make give a default value.', \get_class($component), $refParameter->getName()));
            }
        }

        $component->mount(...$parameters);
    }

    private function getComponent(string $name): object
    {
        if (!$this->components->has($name)) {
            throw new \InvalidArgumentException(sprintf('Unknown component "%s". The registered components are: %s', $name, implode(', ', array_keys($this->components->getProvidedServices()))));
        }

        return $this->components->get($name);
    }
}
