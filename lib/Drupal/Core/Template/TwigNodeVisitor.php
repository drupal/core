<?php

namespace Drupal\Core\Template;

use Twig\Environment;
use Twig\TwigFunction;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;
use Twig\Node\PrintNode;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Provides a TwigNodeVisitor to change the generated parse-tree.
 *
 * This is used to ensure that everything printed is wrapped via the
 * TwigExtension->renderVar() function in order to just write {{ content }}
 * in templates instead of having to write {{ render_var(content) }}.
 *
 * @see twig_render
 */
class TwigNodeVisitor implements NodeVisitorInterface {

  /**
   * Tracks whether there is a render array aware filter active already.
   */
  protected ?bool $skipRenderVarFunction;

  /**
   * {@inheritdoc}
   */
  public function enterNode(Node $node, Environment $env): Node {
    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function leaveNode(Node $node, Environment $env): ?Node {
    // We use this to inject a call to render_var -> TwigExtension->renderVar()
    // before anything is printed.
    if ($node instanceof PrintNode) {
      if (!empty($this->skipRenderVarFunction)) {
        // No need to add the callback, we have escape active already.
        unset($this->skipRenderVarFunction);
        return $node;
      }
      $class = get_class($node);
      $line = $node->getTemplateLine();
      return new $class(
        new FunctionExpression(
          new TwigFunction('render_var', [$env->getExtension(TwigExtension::class), 'renderVar']),
          new Node([$node->getNode('expr')]),
          $line
        ),
        $line
      );
    }
    // Change the 'escape' filter to our own 'drupal_escape' filter.
    elseif ($node instanceof FilterExpression) {
      $name = $node->getAttribute('twig_callable')->getName();
      if ('escape' == $name || 'e' == $name) {
        // Use our own escape filter that is MarkupInterface aware.
        $node->setAttribute('twig_callable', $env->getFilter('drupal_escape'));

        // Store that we have a filter active already that knows
        // how to deal with render arrays.
        $this->skipRenderVarFunction = TRUE;
      }
    }

    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getPriority() {
    // Just above the Optimizer, which is the normal last one.
    return 256;
  }

}
