<?php

namespace HBM\TwigExtensionsBundle\Twig;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Kernel;

class ResponsiveSvgExtension extends \Twig_Extension
{

  /** @var Kernel */
  private $kernel;

  /** @var Logger */
  private $logger;

  private $config;

  public function __construct(Kernel $kernel, $config, Logger $logger)
  {
    $this->kernel = $kernel;
    $this->config = $config;
    $this->logger = $logger;
  }

  public function getFilters()
  {
    return array(
      new \Twig_SimpleFilter('responsiveSVG', array($this, 'generateResponsiveSvg'), ['is_safe' => ['html']]),
      new \Twig_SimpleFilter('responsiveSourceSVG', array($this, 'generateResponsiveSourceSvg'), ['is_safe' => ['html']]),
    );
  }

  public function getName()
  {
    return 'hbm_twig_extensions_responsive_svg';
  }

  /****************************************************************************/
  /* FILTERS                                                                  */
  /****************************************************************************/

  private function resolvePath($file) {
    if (isset($this->config['aliases'][$file]['path'])) {
      return $this->kernel->getRootDir().'/../web/'.$this->config['aliases'][$file]['path'];
    }

    return $file;
  }

  private function resolveUrl($file) {
    if (isset($this->config['aliases'][$file]['path'])) {
      return '/'.$this->config['aliases'][$file]['path'];
    }

    return $file;
  }

  private function loadContent($path) {
    return file_get_contents($path);
  }

  public function generateResponsiveSourceSvg($uri)
  {
    $pathResolved = $this->resolvePath($uri);
    $svgContent = $this->loadContent($pathResolved);

    $svgContent = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $svgContent);
    $svgContent = str_replace('<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">', '', $svgContent);
    $svgContent = str_replace('<svg ', '<svg style="display:none;" ', $svgContent);

    return $svgContent;
  }

  public function generateResponsiveSvg($uri, $config = [])
  {
    $default = [
      'offsetX' => 0,
      'offsetY' => 0,
      'class' => ''
    ];

    $config = array_merge($default, (array) $config);

    list($file, $identifier) = explode('#', $uri);

    $pathResolved = $this->resolvePath($file);
    $urlResolved = $this->resolveUrl($file);

    $href = $urlResolved;
    if ($this->config['inline']) {
      $href = '';
    }
    if (strlen($identifier) > 0) {
      $href .= '#' . $identifier;
    }

    // Parse svg file and read viewBox attribute.
    $svg = $this->loadContent($pathResolved);
    if ($svg === false) {
      $this->logger->debug('Cannot find svg ' . $uri);
      return '';
    }

    $crawler = new Crawler($svg);

    if (strlen($identifier) > 0) {
      $item = $crawler->filter('#' . $identifier);
    } else {
      $item = $crawler;
    }

    if (!$item->count()) {
      $this->logger->debug('Cannot find svg element for ' . $uri);
      return '';
    }

    $viewBox = $item->attr('viewBox');

    if (strlen($viewBox) == 0) {
      $this->logger->debug('Cannot find viewBox attribute in ' . $uri);
      return '';
    }

    // Build markup
    list($x, $y, $width, $height) = explode(' ', $viewBox);

    $width += $config['offsetX'];
    $height += $config['offsetY'];

    if (isset($config['width'])) {
      $width = $config['width'];
    }

    if (isset($config['height'])) {
      $height = $config['height'];
    }

    $padding = round($height / $width * 100, 5);

    $classes = array_merge(['responsive-svg'], explode(' ', $config['class']));

    $dom = new \DOMDocument();

    $wrapper = $dom->createElement('div');
    $wrapper->setAttribute('class', implode(' ', $classes));
    $wrapper->setAttribute('style', 'position: relative;');

    $filler = $dom->createElement('div');
    $filler->setAttribute('style', 'width: 100%; height: 0; overflow-hidden; padding-bottom: ' . $padding . '%');
    $wrapper->appendChild($filler);

    if (strlen($identifier) > 0) {
      $svg = $dom->createElement('svg');
      $svg->setAttribute('viewBox', '0 0 ' . $width . ' ' . $height);

      $use = $dom->createElement('use');
      $use->setAttribute('xlink:href', $href);
      $svg->appendChild($use);
    } else {
      $svg = $dom->createElement('object');
      $svg->setAttribute('type', 'image/svg+xml');
      $svg->setAttribute('data', $href);
    }

    $svg->setAttribute('style', 'position: absolute; top: 0; bottom: 0; left: 0; right: 0;');

    $wrapper->appendChild($svg);
    $dom->appendChild($wrapper);

    return $dom->saveHTML();
  }

}
