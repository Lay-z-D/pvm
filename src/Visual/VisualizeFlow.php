<?php

namespace Formapro\Pvm\Visual;

use Alom\Graphviz\RawText;
use Fhaculty\Graph\Edge\Directed;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;
use Formapro\Pvm\Node;
use Formapro\Pvm\Process;
use Formapro\Pvm\Token;
use Formapro\Pvm\TokenTransition;
use Formapro\Pvm\Transition;
use function Formapro\Values\get_value;
use Illuminate\Support\Facades\DB;
use function Formapro\Values\build_object;
//use Graphp\GraphViz\GraphViz;

class VisualizeFlow
{
  private static $styleCache = null;
  private static $graphSettingsCache = null;
  private static $transitionStyleCache = null;
  private static $specialNodesCache = null;
  private $colorMode = 'light';
  
  private function getNodeTypeStyles(): array
  {
    if (self::$styleCache === null) {
      self::$styleCache = [];
      
      try {
        $styles = DB::table('diagram_node_type_styles')->get();
        foreach ($styles as $style) {
          // Select colors based on color mode
          $color = $this->colorMode === 'dark' ? ($style->color_dark ?? $style->color_light) : $style->color_light;
          $fillcolor = $this->colorMode === 'dark' ? ($style->fillcolor_dark ?? $style->fillcolor_light) : $style->fillcolor_light;
          $fontcolor = $this->colorMode === 'dark' ? ($style->fontcolor_dark ?? $style->fontcolor_light) : $style->fontcolor_light;
          $fillcolor_gradient = $this->colorMode === 'dark' ? ($style->fillcolor_gradient_dark ?? null) : ($style->fillcolor_gradient_light ?? null);
          
          self::$styleCache[$style->type] = [
            'shape' => $style->shape,
            'color' => $color,
            'fillcolor' => $fillcolor_gradient ?? $fillcolor,
            'style' => $style->style,
            'gradientangle' => $style->gradientangle ?? 0,
            'penwidth' => $style->penwidth ?? 1.0,
            'fontname' => $style->fontname ?? 'helvetica',
            'fontsize' => $style->fontsize ?? 10,
            'fontcolor' => $fontcolor,
          ];
        }
      } catch (\Exception $e) {
        // If table doesn't exist yet, use defaults
        self::$styleCache = $this->getDefaultStyles();
      }
    }
    
    return self::$styleCache;
  }
  
  private function getGraphSettings(): array
  {
    if (self::$graphSettingsCache === null) {
      try {
        $settings = DB::table('diagram_graph_settings')->where('name', 'default')->first();
        if ($settings) {
          self::$graphSettingsCache = [
            'rankdir' => $settings->rankdir,
            'ranksep' => $settings->ranksep,
            'size' => $settings->size,
            'fontname' => $settings->fontname,
            'fontsize' => $settings->fontsize,
            'bgcolor_light' => $settings->bgcolor_light ?? 'transparent',
            'bgcolor_dark' => $settings->bgcolor_dark ?? 'transparent',
            'bgcolor_gradient_light' => $settings->bgcolor_gradient_light ?? null,
            'bgcolor_gradient_dark' => $settings->bgcolor_gradient_dark ?? null,
            'graph_style' => $settings->graph_style ?? null,
            'splines' => $settings->splines ?? 'curved',
          ];
        } else {
          self::$graphSettingsCache = $this->getDefaultGraphSettings();
        }
      } catch (\Exception $e) {
        self::$graphSettingsCache = $this->getDefaultGraphSettings();
      }
    }
    
    return self::$graphSettingsCache;
  }
  
  private function getTransitionStyle(): array
  {
    if (self::$transitionStyleCache === null) {
      try {
        $style = DB::table('diagram_transition_styles')->where('name', 'default')->first();
        if ($style) {
          self::$transitionStyleCache = [
            'color_light' => $style->color_light ?? '#808080',
            'color_dark' => $style->color_dark ?? '#b0b0b0',
            'fontname' => $style->fontname,
            'fontsize' => $style->fontsize,
            'fontcolor_light' => $style->fontcolor_light ?? '#000000',
            'fontcolor_dark' => $style->fontcolor_dark ?? '#FFFFFF',
            'style' => $style->style,
            'penwidth' => $style->penwidth,
            'arrowsize' => $style->arrowsize ?? 1.0,
            'taken_style' => $style->taken_style ?? 'solid',
            'taken_color_light' => $style->taken_color_light ?? '#2f65fa',
            'taken_color_dark' => $style->taken_color_dark ?? '#5a8dff',
          ];
        } else {
          self::$transitionStyleCache = $this->getDefaultTransitionStyle();
        }
      } catch (\Exception $e) {
        self::$transitionStyleCache = $this->getDefaultTransitionStyle();
      }
    }
    
    return self::$transitionStyleCache;
  }
  
  private function getSpecialNodeStyle(string $nodeType): array
  {
    if (self::$specialNodesCache === null) {
      self::$specialNodesCache = [];
    }
    
    if (!isset(self::$specialNodesCache[$nodeType])) {
      try {
        $style = DB::table('diagram_special_node_styles')->where('node_type', $nodeType)->first();
        if ($style) {
          // Select colors based on color mode
          $color = $this->colorMode === 'dark' ? ($style->color_dark ?? $style->color_light) : $style->color_light;
          $fillcolor = $this->colorMode === 'dark' ? ($style->fillcolor_dark ?? $style->fillcolor_light) : $style->fillcolor_light;
          $fontcolor = $this->colorMode === 'dark' ? ($style->fontcolor_dark ?? $style->fontcolor_light) : $style->fontcolor_light;
          $fillcolor_gradient = $this->colorMode === 'dark' ? ($style->fillcolor_gradient_dark ?? null) : ($style->fillcolor_gradient_light ?? null);
          
          self::$specialNodesCache[$nodeType] = [
            'label' => $style->label,
            'shape' => $style->shape,
            'color' => $color,
            'fillcolor' => $fillcolor_gradient ?? $fillcolor,
            'style' => $style->style,
            'gradientangle' => $style->gradientangle ?? 0,
            'penwidth' => $style->penwidth ?? 1.0,
            'fontname' => $style->fontname,
            'fontsize' => $style->fontsize,
            'fontcolor' => $fontcolor,
          ];
        } else {
          self::$specialNodesCache[$nodeType] = $this->getDefaultSpecialNodeStyle($nodeType);
        }
      } catch (\Exception $e) {
        self::$specialNodesCache[$nodeType] = $this->getDefaultSpecialNodeStyle($nodeType);
      }
    }
    
    return self::$specialNodesCache[$nodeType];
  }
  
  private function getDefaultStyles(): array
  {
    return [
      'gateway' => ['shape' => 'diamond', 'color' => '#d4b102', 'fillcolor' => '#fffabf', 'style' => 'rounded,filled', 'fontname' => 'helvetica', 'fontsize' => 10, 'fontcolor' => '#000000'],
      'diagram' => ['shape' => 'doubleoctagon', 'color' => 'orange', 'fillcolor' => '#ffe396', 'style' => 'rounded,filled', 'fontname' => 'helvetica', 'fontsize' => 10, 'fontcolor' => '#000000'],
      'medication_decision' => ['shape' => 'box', 'color' => 'purple', 'fillcolor' => '#ddadff', 'style' => 'rounded,filled', 'fontname' => 'helvetica', 'fontsize' => 10, 'fontcolor' => '#000000'],
      'notification' => ['shape' => 'box', 'color' => '#56c7c4', 'fillcolor' => '#bff2f1', 'style' => 'rounded,filled', 'fontname' => 'helvetica', 'fontsize' => 10, 'fontcolor' => '#000000'],
      'output_array' => ['shape' => 'box', 'color' => '#57992b', 'fillcolor' => '#bcff8f', 'style' => 'rounded,filled', 'fontname' => 'helvetica', 'fontsize' => 10, 'fontcolor' => '#000000'],
      'output' => ['shape' => 'box', 'color' => '#57992b', 'fillcolor' => '#bcff8f', 'style' => 'rounded,filled', 'fontname' => 'helvetica', 'fontsize' => 10, 'fontcolor' => '#000000'],
      'output_behaviour' => ['shape' => 'box', 'color' => '#57992b', 'fillcolor' => '#bcff8f', 'style' => 'rounded,filled', 'fontname' => 'helvetica', 'fontsize' => 10, 'fontcolor' => '#000000'],
      'component' => ['shape' => 'component', 'color' => 'orange', 'fillcolor' => '#f0f0f0', 'style' => 'rounded,filled', 'fontname' => 'helvetica', 'fontsize' => 10, 'fontcolor' => '#000000'],
      'default' => ['shape' => 'box', 'color' => '#a6a6a6', 'fillcolor' => '#f0f0f0', 'style' => 'rounded,filled', 'fontname' => 'helvetica', 'fontsize' => 10, 'fontcolor' => '#000000'],
    ];
  }
  
  private function getDefaultGraphSettings(): array
  {
    return [
      'rankdir' => 'TB',
      'ranksep' => 0.2,
      'size' => '10,100',
      'fontname' => 'helvetica',
      'fontsize' => 10,
      'bgcolor_light' => 'transparent',
      'bgcolor_dark' => 'transparent',
      'bgcolor_gradient_light' => null,
      'bgcolor_gradient_dark' => null,
      'graph_style' => null,
      'splines' => 'curved',
    ];
  }
  
  private function getDefaultTransitionStyle(): array
  {
    return [
      'color_light' => '#808080',
      'color_dark' => '#b0b0b0',
      'fontname' => 'helvetica',
      'fontsize' => 10,
      'fontcolor_light' => '#000000',
      'fontcolor_dark' => '#FFFFFF',
      'style' => 'solid',
      'penwidth' => 1,
      'arrowsize' => 1.0,
      'taken_style' => 'solid',
      'taken_color_light' => '#2f65fa',
      'taken_color_dark' => '#5a8dff',
    ];
  }
  
  private function getDefaultSpecialNodeStyle(string $nodeType): array
  {
    if ($nodeType === 'start') {
      return [
        'label' => 'Start',
        'shape' => 'circle',
        'color' => '#2f65fa',
        'fillcolor' => 'lightblue',
        'style' => 'filled',
        'fontname' => 'helvetica',
        'fontsize' => 10,
        'fontcolor' => '#000000',
      ];
    } else { // end
      return [
        'label' => 'End',
        'shape' => 'circle',
        'color' => '#fa4141',
        'fillcolor' => '#ff8c8c',
        'style' => 'filled',
        'fontname' => 'helvetica',
        'fontsize' => 10,
        'fontcolor' => '#000000',
      ];
    }
  }
  
  public function createGraph(Process $process, $color = 'light')
  {
    // Store color mode for use in transition methods
    $this->colorMode = $color;
    
    // Clear caches to ensure colors are loaded with correct mode
    self::$styleCache = null;
    self::$specialNodesCache = null;
    
    $graphSettings = $this->getGraphSettings();
    
    // Select appropriate bgcolor and gradient based on color mode
    $bgcolor_key = $color === 'dark' ? 'bgcolor_dark' : 'bgcolor_light';
    $gradient_key = $color === 'dark' ? 'bgcolor_gradient_dark' : 'bgcolor_gradient_light';
    
    // Use gradient if available, otherwise use solid color
    $bgcolor = $graphSettings[$gradient_key] ?? $graphSettings[$bgcolor_key];
    
    // Reverse gradient direction (swap colors in gradient string)
    if (strpos($bgcolor, ':') !== false) {
      $colors = explode(':', $bgcolor);
      $bgcolor = $colors[1] . ':' . $colors[0];
    }
    
    $graphStyle = $graphSettings['graph_style'];
    
    $graph = new Graph();
    $graph->setAttribute('graphviz.graph.rankdir', $graphSettings['rankdir']);
    $graph->setAttribute('graphviz.graph.ranksep', $graphSettings['ranksep']);
    $graph->setAttribute('graphviz.graph.bgcolor', $bgcolor);
    $graph->setAttribute('graphviz.graph.splines', $graphSettings['splines']);
    if ($graphStyle) {
      $graph->setAttribute('graphviz.graph.style', $graphStyle);
    }
    $graph->setAttribute('alom.graphviz', [
    'rankdir' => $graphSettings['rankdir'],
    'ranksep' => $graphSettings['ranksep'],
	  'size' => $graphSettings['size'],
	  'fontname' => $graphSettings['fontname'],
	  'bgcolor' => $bgcolor,
	  'splines' => $graphSettings['splines'],
	  'style' => $graphStyle ?: null,
    'pad' => '0.20',
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
   * @param bool $showExceptions Whether to color nodes red on exceptions (default: true)
   */
  public function applyTokens(Graph $graph, Process $process, array $tokens = [], bool $showExceptions = true)
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

        if ($hasException && $showExceptions) {
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

    // Get styles from database or defaults
    $styles = $this->getNodeTypeStyles();
    $nodeType = $node->getOption('type') ?? 'default';
    $styleConfig = $styles[$nodeType] ?? $styles['default'];
    
    $shape = $styleConfig['shape'];
    $color = $styleConfig['color'];
    $fillcolor = $styleConfig['fillcolor'];
    $style = $styleConfig['style'];
    $fontname = $styleConfig['fontname'];
    $fontsize = $styleConfig['fontsize'];
    $fontcolor = $styleConfig['fontcolor'];

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
      'fontsize' => $fontsize,
      'shape' => $shape,
      'fillcolor' => $styleConfig['fillcolor'],
      'style' => $style,
      'gradientangle' => $styleConfig['gradientangle'] ?? 0,
      'penwidth' => $styleConfig['penwidth'] ?? 1.0,
	  'fontname' => $fontname,
	  'fontcolor' => $fontcolor,
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
    $transitionStyle = $this->getTransitionStyle();
    $fontcolor = $this->colorMode === 'dark' ? $transitionStyle['fontcolor_dark'] : $transitionStyle['fontcolor_light'];
    $color = $this->colorMode === 'dark' ? $transitionStyle['color_dark'] : $transitionStyle['color_light'];
    $to = $graph->getVertex($transition->getTo()->getId());

    $edge = $from->createEdgeTo($to);
    $edge->setAttribute('pvm.transition_id', $transition->getId());
    $edge->setAttribute('graphviz.id', $transition->getId());
    $edge->setAttribute('graphviz.label', $transition->getName());

    $edge->setAttribute('alom.graphviz', [
      'label' => $transition->getName(),
      'id' => $transition->getId(),
	  'fontname' => $transitionStyle['fontname'],
	  'fontsize' => $transitionStyle['fontsize'],
	  'fontcolor' => $fontcolor,
	  'color' => $color,
	  'style' => $transitionStyle['style'],
	  'penwidth' => $transitionStyle['penwidth'],
	  'arrowsize' => $transitionStyle['arrowsize'] ?? 1.0,
    ]);
  }

  private function createEndTransition(Graph $graph, Vertex $to, Transition $transition)
  {
    $transitionStyle = $this->getTransitionStyle();
    $fontcolor = $this->colorMode === 'dark' ? $transitionStyle['fontcolor_dark'] : $transitionStyle['fontcolor_light'];
    $color = $this->colorMode === 'dark' ? $transitionStyle['color_dark'] : $transitionStyle['color_light'];
    $from = $graph->getVertex($transition->getTo()->getId());

    if ($from->hasEdgeTo($to)) {
      $edge = $from->getEdgesTo($to)->getEdgeFirst();
    } else {
      $edge = $from->createEdgeTo($to);
    }

    $edge->setAttribute('graphviz.label', $transition->getName());
    $edge->setAttribute('graphviz.id', $transition->getId() . '_end');
    $edge->setAttribute('pvm.transition_id', $transition->getId() . '_end');

    $edge->setAttribute('alom.graphviz', [
      //'label' => $transition->getName(),
      'id' => $transition->getId() . '_end',
	  'fontname' => $transitionStyle['fontname'],
	  'fontsize' => $transitionStyle['fontsize'],
	  'fontcolor' => $fontcolor,
	  'color' => $color,
	  'style' => $transitionStyle['style'],
	  'penwidth' => $transitionStyle['penwidth'],
	  'arrowsize' => $transitionStyle['arrowsize'] ?? 1.0,
    ]);
  }

  private function createMiddleTransition(Graph $graph, Transition $transition)
  {
    $transitionStyle = $this->getTransitionStyle();
    $fontcolor = $this->colorMode === 'dark' ? $transitionStyle['fontcolor_dark'] : $transitionStyle['fontcolor_light'];
    $color = $this->colorMode === 'dark' ? $transitionStyle['color_dark'] : $transitionStyle['color_light'];
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
      'URL' => "javascript:window.parent.Livewire.dispatch('initiateNodeEditInManager', { nodeId: '" . $transition->getDatabaseId() . "' });",
      'fontname' => $transitionStyle['fontname'],
	    'fontsize' => $transitionStyle['fontsize'],
	    'fontcolor' => $fontcolor,
      'color' => $color,
      'style' => $transitionStyle['style'],
      'penwidth' => $transitionStyle['penwidth'],
      'arrowsize' => $transitionStyle['arrowsize'] ?? 1.0,
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
      $style = $this->getSpecialNodeStyle('start');
      
      $vertex = $graph->createVertex('__start');
      $vertex->setAttribute('graphviz.label', $style['label']);
      $vertex->setAttribute('graphviz.color', $style['color']);
      $vertex->setAttribute('graphviz.shape', $style['shape']);

      $vertex->setAttribute('alom.graphviz', [
        'label' => $style['label'],
        'color' => $style['color'],
        'fillcolor' => $style['fillcolor'],
        'style' => $style['style'],
        'gradientangle' => $style['gradientangle'] ?? 0,
        'penwidth' => $style['penwidth'] ?? 1.0,
        'shape' => $style['shape'],
		'fontname' => $style['fontname'],
		'fontsize' => $style['fontsize'],
		'fontcolor' => $style['fontcolor'],
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
      $style = $this->getSpecialNodeStyle('end');
      
      $vertex = $graph->createVertex('__end');
      $vertex->setAttribute('graphviz.label', $style['label']);
      $vertex->setAttribute('graphviz.color', $style['color']);
      $vertex->setAttribute('graphviz.shape', $style['shape']);

      $vertex->setAttribute('alom.graphviz', [
        'label' => $style['label'],
        'color' => $style['color'],
        'fillcolor' => $style['fillcolor'],
        'style' => $style['style'],
        'gradientangle' => $style['gradientangle'] ?? 0,
        'penwidth' => $style['penwidth'] ?? 1.0,
        'shape' => $style['shape'],
		'fontname' => $style['fontname'],
		'fontsize' => $style['fontsize'],
		'fontcolor' => $style['fontcolor'],
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