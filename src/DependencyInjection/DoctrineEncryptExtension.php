<?php

namespace Ambta\DoctrineEncryptBundle\DependencyInjection;

use Ambta\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Ambta\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Initialization of bundle.
 *
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class DoctrineEncryptExtension extends Extension
{
    /**
     * Flag to test if we should wrap exceptions by our own exceptions.
     *
     * @internal
     */
    public static $wrapExceptions = false;

    public const SupportedEncryptorClasses = [
        'Defuse' => DefuseEncryptor::class,
        'Halite' => HaliteEncryptor::class,
    ];

    public function load(array $configs, ContainerBuilder $container): void
    {
        // Create configuration object
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        // If empty encryptor class, use Halite encryptor
        if (array_key_exists($config['encryptor_class'], self::SupportedEncryptorClasses)) {
            $config['encryptor_class_full'] = self::SupportedEncryptorClasses[$config['encryptor_class']];
        } else {
            $config['encryptor_class_full'] = $config['encryptor_class'];
        }

        // Set parameters
        $container->setParameter('ambta_doctrine_encrypt.encryptor_class_name', $config['encryptor_class_full']);
        $container->setParameter('ambta_doctrine_encrypt.secret_directory_path', $config['secret_directory_path']);
        $container->setParameter('ambta_doctrine_encrypt.enable_secret_generation', $config['enable_secret_generation']);

        if (isset($config['secret'])) {
            $container->setParameter('ambta_doctrine_encrypt.secret', $config['secret']);
        }

        // Load service file
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        if (!isset($config['secret'])) {
            $loader->load('services_with_secretfactory.yml');
        } else {
            $loader->load('services_with_secret.yml');
        }

        // Symfony 1-4
        // Sanity-check since this should be blocked by composer.json
        if (Kernel::MAJOR_VERSION < 5 || (Kernel::MAJOR_VERSION === 5 && Kernel::MINOR_VERSION < 4)) {
            throw new \RuntimeException('doctrineencryptbundle/doctrine-encrypt-bundle expects symfony-version >= 5.4!');
        }

        // Symfony 5-6
        if (Kernel::MAJOR_VERSION < 7) {
            // PHP 7.x (no attributes)
            if (PHP_VERSION_ID < 80000) {
                $loader->load('services_subscriber_with_annotations.yml');
            // PHP 8.x (annotations and attributes)
            } else {
                // Doctrine 3.0 - no annotations
                if (\Composer\InstalledVersions::satisfies(new \Composer\Semver\VersionParser(), 'doctrine/orm', '^3.0')) {
                    $loader->load('service_listeners_with_attributes.yml');
                } else {
                    $loader->load('services_subscriber_with_annotations_and_attributes.yml');
                }
            }
        // Symfony 7 (only attributes)
        } else {
            $loader->load('service_listeners_with_attributes.yml');
        }

        // Wrap exceptions
        if ($config['wrap_exceptions']) {
            self::$wrapExceptions = true;
        } else {
            trigger_deprecation(
                'doctrineencryptbundle/doctrine-encrypt-bundle',
                '5.4.2',
                <<<'EOF'
Starting from 6.0, all exceptions thrown by this library will be wrapped by \Ambta\DoctrineEncryptBundle\Exception\DoctrineEncryptBundleException or a child-class of it.
You can start using these exceptions today by setting 'ambta_doctrine_encrypt.wrap_exceptions' to TRUE.
EOF
            );
        }
    }

    /**
     * Get alias for configuration.
     */
    public function getAlias(): string
    {
        return 'ambta_doctrine_encrypt';
    }
}
