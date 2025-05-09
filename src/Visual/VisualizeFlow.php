<?php

namespace Formapro\Pvm\Visual;

use Formapro\Pvm\Node;
use Formapro\Pvm\Token;
use Formapro\Pvm\Process;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;
use Alom\Graphviz\RawText;
use Formapro\Pvm\Transition;
use Graphp\GraphViz\GraphViz;
use Fhaculty\Graph\Edge\Directed;
use Formapro\Pvm\TokenTransition;
use function Formapro\Values\get_value;
use function Formapro\Values\build_object;

class VisualizeFlow
{

  public function createGraph(Process $process)
  {
    $graph = new Graph();
    $graph->setAttribute('graphviz.graph.rankdir', 'TB');
    $graph->setAttribute('graphviz.graph.ranksep', 1);
//        $graph->setAttribute('graphviz.graph.constraint', false);
//        $graph->setAttribute('graphviz.graph.splines', 'ortho');
    $graph->setAttribute('alom.graphviz', [
      'rankdir' => 'TB',
      'ranksep' => 0.2,
	  'size' => '10,100',
	  'fontname' => 'helvetica',
    ]);

    $startVertex = $this->createStartVertex($graph);
    $endVertex = $this->createEndVertex($graph);

    foreach ($process->getNodes() as $node)
    {
      $this->createVertex($graph, $node);
    }

    //$ended = false; gives error: "Fhaculty\Graph\Exception\UnderflowException is caught. Message Does not contain any edges"

    foreach ($process->getTransitions() as $transition)
    {
      if (false == $transition->getFrom() && $transition->getTo()) {
        $this->createStartTransition($graph, $startVertex, $transition);
      }

      if ($transition->getFrom() && $transition->getTo()) {
        $this->createMiddleTransition($graph, $transition);
      }

      // if (1 === count($process->getInTransitions($transition->getTo())) && empty($process->getOutTransitions($transition->getTo()))) {
      //   $this->createEndTransition($graph, $endVertex, $transition);
      // } else if (false === $ended && empty($process->getOutTransitions($transition->getTo()))) {
      //   $ended = true;
      //   $this->createEndTransition($graph, $endVertex, $transition);
      // }

	  // Changed commented part to original to avoid error mentioned in line 43
	  if (empty($process->getOutTransitions($transition->getTo()))) {
                $this->createEndTransition($graph, $endVertex, $transition);
      }

    }

    return $graph;
  }

  /**
   * @param Graph $graph
   * @param Process $process
   * @param Token[] $tokens
   */
  public function applyTokens(Graph $graph, Process $process, array $tokens = [])
  {
    $endVertex = $this->createEndVertex($graph);

    foreach ($tokens as $token)
    {
      foreach ($token->getTransitions() as $tokenTransition)
      {

        $hasException = get_value($tokenTransition, 'exception', false);

        $transition = $tokenTransition->getTransition();
        $edge = $this->findTransitionEdge($graph, $transition);

        $alomEdgeAttributes = $edge->getAttribute('alom.graphviz', []);

        if ($edge->getAttribute('pvm.state') === TokenTransition::STATE_PASSED) {
          continue;
        }

        $edge->setAttribute('pvm.state', $tokenTransition->getState());
        $edge->setAttribute('graphviz.color', $this->guessTransitionColor($tokenTransition));
        $alomEdgeAttributes['color'] = $this->guessTransitionColor($tokenTransition);

        if ($hasException) {
          $edge->getVertexEnd()->setAttribute('graphviz.color', 'red');

          $vertexEndAlomAttributes = $edge->getVertexEnd()->getAttribute('alom.graphviz', []);
          $vertexEndAlomAttributes['color'] = 'red';

          $edge->getVertexEnd()->setAttribute('alom.graphviz', $vertexEndAlomAttributes);
        }

        if (empty($process->getOutTransitions($transition->getTo()))) {
          $from = $graph->getVertex($transition->getTo()->getId());
          $endEdge = $from->getEdgesTo($endVertex)->getEdgeFirst();

          if ($edge->getAttribute('pvm.state') === TokenTransition::STATE_PASSED) {
            $endEdge->setAttribute('pvm.state', $tokenTransition->getState());
            $endEdge->setAttribute('graphviz.color', $this->guessTransitionColor($tokenTransition));

            $endEdgeAlomAttribute = $endEdge->getAttribute('alom.graphviz', []);
            $endEdgeAlomAttribute['color'] = $this->guessTransitionColor($tokenTransition);
            $endEdge->setAttribute('alom.graphviz', $endEdgeAlomAttribute);
          }
        }

        $edge->setAttribute('alom.graphviz', $alomEdgeAttributes);
      }
    }
  }

  public function createImageSrc(Graph $graph)
  {
    return (new GraphViz())->createImageSrc($graph);
  }

  public function display(Graph $graph)
  {
    (new GraphViz())->display($graph);
  }

  private function createVertex(Graph $graph, Node $node)
  {
    /** @var Options $options */
    $options = build_object(Options::class, get_value($node, 'visual', []));

    $vertex = $graph->createVertex($node->getId());
    $vertex->setAttribute('graphviz.label', $node->getLabel() ?: $node->getId());
    $vertex->setAttribute('graphviz.id', $node->getId());

    if (null !== $groupId = $node->getOption('group')) {
      $vertex->setAttribute('alom.graphviz_subgroup', $groupId);
    }

    //$shape = $this->getNodeShape($options);

	switch ($node->getOption('type')) {  // original was "switch ($options->getType())"

        case 'gateway':
            $shape = 'diamond';
            $color = '#a6a6a6';
            $fillcolor = '#f0f0f0';
            break;
        case 'diagram':
            $shape = 'doubleoctagon';
            $color = 'orange';
            $fillcolor = '#ffe396';
            $style = 'bold';
            break;
        case 'component':
            $shape = 'component';
            $color = 'orange';
            $fillcolor = '#f0f0f0';
            break;
        default:
            $shape = 'box';
            $fillcolor = '#f0f0f0';
            $style = 'solid';
            $color = '#a6a6a6';
    }

        $style = 'rounded,filled';

	if(!$color) { $color = $node->getConfig('visual.color') ?? '#a6a6a6'; }

    $vertex->setAttribute('graphviz.shape', $shape);

    $label = ($node->getLabel() ?: $node->getId());
    $tooltip = $node->getConfig('visual.tooltip') ?? $label;
    $databaseId = $node->getOption('database_id'); 

    $vertex->setAttribute('alom.graphviz', [
      'id' => $node->getId(), 
      'label' => new RawText('"' . $label . '"'),
      'tooltip' => $tooltip,
      'URL' => "javascript:window.parent.Livewire.dispatch('initiateNodeEditInManager', { nodeId: " . $databaseId . " });", 
      'color' => $color,
      'fontsize' => 10,
      'shape' => $shape,
      'fillcolor' => $fillcolor,
      'style' => $style,
	  'fontname' => 'helvetica',
    ]);

    return $vertex;
  }

  /**
   * @param Options $options
   * @return string
   */
  private function getNodeShape(Options $options): string
  {
    if ($options->getType() === 'gateway') {
      return 'diamond';
    }

    if (!empty($options->getType())) {
      return $options->getType();
    }

    return 'box';
  }

  private function createStartTransition(Graph $graph, Vertex $from, Transition $transition)
  {
    $to = $graph->getVertex($transition->getTo()->getId());

    $edge = $from->createEdgeTo($to);
    $edge->setAttribute('pvm.transition_id', $transition->getId());
    $edge->setAttribute('graphviz.id', $transition->getId());
    $edge->setAttribute('graphviz.label', $transition->getName());

    $edge->setAttribute('alom.graphviz', [
      'label' => $transition->getName(),
      'id' => $transition->getId(),
	  'fontname' => 'helvetica',
	  'fontsize' => 10,
	  'color' => '#808080',
    ]);
  }

  private function createEndTransition(Graph $graph, Vertex $to, Transition $transition)
  {
    $from = $graph->getVertex($transition->getTo()->getId());

    if ($from->hasEdgeTo($to)) {
      $edge = $from->getEdgesTo($to)->getEdgeFirst();
    } else {
      $edge = $from->createEdgeTo($to);
    }

    $edge->setAttribute('graphviz.label', $transition->getName());
    $edge->setAttribute('graphviz.id', $transition->getId());
    $edge->setAttribute('pvm.transition_id', $transition->getId());

    $edge->setAttribute('alom.graphviz', [
      //'label' => $transition->getName(),
      'id' => $transition->getId(),
	  'fontname' => 'helvetica',
	  'fontsize' => 10,
	  'color' => '#808080',
    ]);
  }

  private function createMiddleTransition(Graph $graph, Transition $transition)
  {
    $from = $graph->getVertex($transition->getFrom()->getId());
    $to = $graph->getVertex($transition->getTo()->getId());

    $edge = $from->createEdgeTo($to);
    $edge->setAttribute('pvm.transition_id', $transition->getId());
    $edge->setAttribute('graphviz.id', $transition->getId());
    $edge->setAttribute(
      'graphviz.label',
      $transition->getName()
    );

    $edge->setAttribute('alom.graphviz', [
      'id' => $transition->getId(),
      'label' => $transition->getName(),
      'URL' => "javascript:window.parent.Livewire.dispatch('initiateTransitionEditInManager', { transitionId: '" . $transition->getDatabaseId() . "' });",
	  'fontname' => 'helvetica',
	  'fontsize' => 10,
      'color' => '#808080',
    ]);
  }

  /**
   * @param Graph $graph
   *
   * @return Vertex
   */
  private function createStartVertex(Graph $graph)
  {
    if (false == $graph->hasVertex('__start')) {
      $vertex = $graph->createVertex('__start');
      $vertex->setAttribute('graphviz.label', 'Start');
      $vertex->setAttribute('graphviz.color', 'blue');
      $vertex->setAttribute('graphviz.shape', 'circle');

      $vertex->setAttribute('alom.graphviz', [
        'label' => 'Start',
        'color' => '#2f65fa',
        'fillcolor' => 'lightblue',
        'style' => 'filled',
        'shape' => 'circle',
		'fontname' => 'helvetica',
		'fontsize' => 10,
      ]);
    }

    return $graph->getVertex('__start');
  }

  /**
   * @param Graph $graph
   *
   * @return Vertex
   */
  private function createEndVertex(Graph $graph)
  {
    if (false == $graph->hasVertex('__end')) {
      $vertex = $graph->createVertex('__end');
      $vertex->setAttribute('graphviz.label', 'End');
      $vertex->setAttribute('graphviz.color', 'red');
      $vertex->setAttribute('graphviz.shape', 'circle');

      $vertex->setAttribute('alom.graphviz', [
        'label' => 'End',
        'color' => '#fa4141',
        'fillcolor' => '#ff8c8c',
        'style' => 'filled',
        'shape' => 'circle',
		'fontname' => 'helvetica',
		'fontsize' => 10,
      ]);
    }

    return $graph->getVertex('__end');
  }

  private function guessTransitionColor(TokenTransition $transition): string
  {
    switch ($transition->getState()) {
      case TokenTransition::STATE_INTERRUPTED:
        $transitionColor = 'red';
        break;
      case TokenTransition::STATE_PASSED:
        $transitionColor = '#2f65fa';
        break;
      case TokenTransition::STATE_WAITING:
        $transitionColor = 'orange';
        break;
      default:
        $transitionColor = '#808080';
    }

    return $transitionColor;
  }

  private function findTransitionEdge(Graph $graph, Transition $transition): Directed
  {
    foreach ($graph->getEdges() as $edge) {
      /** @var Directed $edge */

      if ($edge->getAttribute('pvm.transition_id') === $transition->getId()) {
        return $edge;
      }
    }

    throw new \LogicException(sprintf('The edge for transition "%s" could not be found.', $transition->getId()));
  }
}