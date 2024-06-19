<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\Compiler;

use Drupal\Core\DependencyInjection\Compiler\StackedKernelPass;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\StackMiddleware\StackedHttpKernel;
use Drupal\Tests\Core\DependencyInjection\Fixture\FinalTestHttpMiddlewareClass;
use Drupal\Tests\Core\DependencyInjection\Fixture\FinalTestNonTerminableHttpMiddlewareClass;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @coversDefaultClass \Drupal\Core\DependencyInjection\Compiler\StackedKernelPass
 * @group DependencyInjection
 */
class StackedKernelPassTest extends UnitTestCase {

  /**
   * The stacked kernel pass.
   *
   * @var \Drupal\Core\DependencyInjection\Compiler\StackedKernelPass
   */
  protected $stackedKernelPass;

  /**
   * @var \Drupal\Core\DependencyInjection\Container
   */
  protected $containerBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->stackedKernelPass = new StackedKernelPass();
    $this->containerBuilder = new ContainerBuilder();
  }

  /**
   * @covers ::process
   */
  public function testProcessWithStackedKernel(): void {
    $stacked_kernel = new Definition(StackedHttpKernel::class);
    $stacked_kernel->setPublic(TRUE);
    $this->containerBuilder->setDefinition('http_kernel', $stacked_kernel);
    $this->containerBuilder->setDefinition('http_kernel.basic', $this->createMiddlewareServiceDefinition(FALSE, 0));

    $this->containerBuilder->setDefinition('http_kernel.three', $this->createMiddlewareServiceDefinition());
    $this->containerBuilder->setDefinition('http_kernel.one', $this->createMiddlewareServiceDefinition(TRUE, 10));
    $this->containerBuilder->setDefinition('http_kernel.two', $this->createMiddlewareServiceDefinition(TRUE, 5));

    $this->stackedKernelPass->process($this->containerBuilder);

    $stacked_kernel_args = $this->containerBuilder->getDefinition('http_kernel')->getArguments();

    // Check the stacked kernel args.
    $this->assertSame('http_kernel.one', (string) $stacked_kernel_args[0]);
    $this->assertCount(4, $stacked_kernel_args[1]);
    $this->assertSame('http_kernel.one', (string) $stacked_kernel_args[1][0]);
    $this->assertSame('http_kernel.two', (string) $stacked_kernel_args[1][1]);
    $this->assertSame('http_kernel.three', (string) $stacked_kernel_args[1][2]);
    $this->assertSame('http_kernel.basic', (string) $stacked_kernel_args[1][3]);

    // Check the modified definitions.
    $definition = $this->containerBuilder->getDefinition('http_kernel.one');
    $args = $definition->getArguments();
    $this->assertSame('http_kernel.two', (string) $args[0]);
    $this->assertSame('test', $args[1]);

    $definition = $this->containerBuilder->getDefinition('http_kernel.two');
    $args = $definition->getArguments();
    $this->assertSame('http_kernel.three', (string) $args[0]);
    $this->assertSame('test', $args[1]);

    $definition = $this->containerBuilder->getDefinition('http_kernel.three');
    $args = $definition->getArguments();
    $this->assertSame('http_kernel.basic', (string) $args[0]);
    $this->assertSame('test', $args[1]);
  }

  /**
   * @covers ::process
   */
  public function testProcessWithHttpKernel(): void {
    $kernel = new Definition('Symfony\Component\HttpKernel\HttpKernelInterface');
    $kernel->setPublic(TRUE);
    $this->containerBuilder->setDefinition('http_kernel', $kernel);
    $this->stackedKernelPass->process($this->containerBuilder);

    $unprocessed_kernel = $this->containerBuilder->getDefinition('http_kernel');

    $this->assertSame($kernel, $unprocessed_kernel);
    $this->assertSame($kernel->getArguments(), $unprocessed_kernel->getArguments());
  }

  /**
   * Tests that class declared 'final' can be added as http_middleware.
   */
  public function testProcessWithStackedKernelAndFinalHttpMiddleware(): void {
    $stacked_kernel = new Definition(StackedHttpKernel::class);
    $stacked_kernel->setPublic(TRUE);
    $this->containerBuilder->setDefinition('http_kernel', $stacked_kernel);
    $basic_kernel = $this->getMockBuilder(HttpKernel::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['handle', 'terminate'])
      ->getMock();
    $basic_definition = (new Definition($basic_kernel::class))
      ->setPublic(TRUE);
    $this->containerBuilder->setDefinition('http_kernel.basic', $basic_definition);

    // Services tagged 'http_middleware', other than the highest priority
    // middleware that is a responder, is also set as lazy by
    // StackedKernelPass::process(). Add middleware classes declared final and
    // confirm they are interface proxied correctly.
    // @see https://symfony.com/doc/current/service_container/lazy_services.html#interface-proxifying
    $first_responder = $this->getMockBuilder(HttpKernelInterface::class)
      ->getMock();
    $this->containerBuilder->setDefinition('http_kernel.one', (new Definition($first_responder::class))
      ->setPublic(TRUE)
      ->addTag('http_middleware', [
        'priority' => 200,
        'responder' => TRUE,
      ]));
    // First middleware class declared final.
    $this->containerBuilder->setDefinition('http_kernel.two', (new Definition(FinalTestHttpMiddlewareClass::class))
      ->setPublic(TRUE)
      ->addTag('http_middleware', [
        'priority' => 100,
        'responder' => TRUE,
      ]));
    // Second middleware class declared final, this time without implementing
    // TerminableInterface.
    $this->containerBuilder->setDefinition('http_kernel.three', (new Definition(FinalTestNonTerminableHttpMiddlewareClass::class))
      ->setPublic(TRUE)
      ->addTag('http_middleware', [
        'priority' => 50,
        'responder' => TRUE,
      ]));
    $this->stackedKernelPass->process($this->containerBuilder);
    try {
      $this->containerBuilder->get('http_kernel');
    }
    catch (InvalidArgumentException $e) {
      $this->fail($e->getMessage());
    }
  }

  /**
   * Creates a middleware definition.
   *
   * @param bool $tag
   *   Whether or not to set the http_middleware tag.
   * @param int $priority
   *   The priority to be used for the tag.
   *
   * @return \Symfony\Component\DependencyInjection\Definition
   */
  protected function createMiddlewareServiceDefinition($tag = TRUE, $priority = 0) {
    $definition = new Definition('Symfony\Component\HttpKernel\HttpKernelInterface', ['test']);
    $definition->setPublic(TRUE);

    if ($tag) {
      $definition->addTag('http_middleware', ['priority' => $priority]);
    }

    return $definition;
  }

}
