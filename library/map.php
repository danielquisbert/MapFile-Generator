<?php
namespace MapFile;

require_once('class.php');
require_once('exception.php');
require_once('label.php');
require_once('layer.php');
require_once('legend.php');
require_once('scalebar.php');
require_once('style.php');

class Map {
  private $fontsetfilename;
  private $symbolsetfilename;
  private $metadata = array();

  private $_layers = array();

  public $extent = array(-1, -1, -1, -1);
  public $height = 500;
  public $name = 'MYMAP';
  public $projection;
  public $status = self::STATUS_ON;
  public $units = self::UNITS_METERS;
  public $width = 500;

  public $legend;
  public $scalebar;

  const STATUS_ON = 1;
  const STATUS_OFF = 0;

  const UNITS_INCHES = 0;
  const UNITS_FEET = 1;
  const UNITS_MILES = 2;
  const UNITS_METERS = 3;
  const UNITS_KILOMETERS = 4;
  const UNITS_DD = 5;
  const UNITS_PIXELS = 6;
  const UNITS_NAUTICALMILES = 8;

  public function __construct($mapfile = NULL) {
    if (!is_null($mapfile) && file_exists($mapfile)) $this->read($mapfile);

    if (is_null($this->legend)) $this->legend = new Legend();
    if (is_null($this->scalebar)) $this->scalebar = new Scalebar();
  }

  public function setExtent($minx, $miny, $maxx, $maxy) {
    $this->extent = array($minx, $miny, $maxx, $maxy);
  }
  public function setFontSet($filename) {
    if (file_exists($filename)) $this->fontsetfilename = $filename; else throw new Exception('FontSet file does not exists.');
  }
  public function setMetadata($key, $value) {
    $this->metadata[$key] = $value;
  }
  public function setSize($width, $height) {
    $this->width = intval($width);
    $this->height = intval($height);
  }
  public function setSymbolSet($filename) {
    if (file_exists($filename)) $this->symbolsetfilename = $filename; else throw new Exception('SymbolSet file does not exists.');
  }

  public function getLayers() {
    return $this->_layers;
  }
  public function getLayer($i) {
    return (isset($this->_layers[$i]) ? $this->_layers[$i] : FALSE);
  }
  public function getMetadata($key) {
    return (isset($this->metadata[$key]) ? $this->metadata[$key] : FALSE);
  }

  public function removeMetadata($key) {
    if (isset($this->metadata[$key])) unset($this->metadata[$key]);
  }

  public function addLayer($layer = NULL) {
    if (is_null($layer)) $layer = new Layer();
    $count = array_push($this->_layers, $layer);
    return $this->_layers[$count-1];
  }

  public function save($filename) {
    $f = fopen($filename, 'w');
    fwrite($f, 'MAP'.PHP_EOL);

    fwrite($f, '  STATUS '.$this->convertStatus().PHP_EOL);
    fwrite($f, '  NAME "'.$this->name.'"'.PHP_EOL);
    if (!empty($this->extent) && array_sum($this->extent) >= 0) fwrite($f, '  EXTENT '.implode(' ',$this->extent).PHP_EOL);
    if (!empty($this->fontsetfilename)) fwrite($f, '  FONTSET "'.$this->fontsetfilename.'"'.PHP_EOL);
    if (!empty($this->symbolsetfilename)) fwrite($f, '  SYMBOLSET "'.$this->symbolsetfilename.'"'.PHP_EOL);
    if (!empty($this->width) && !empty($this->height)) fwrite($f, '  SIZE '.$this->width.' '.$this->height.PHP_EOL);
    if (!is_null($this->units)) fwrite($f, '  UNITS '.$this->convertUnits().PHP_EOL);

    if (!empty($this->projection)) {
      fwrite($f, PHP_EOL);
      fwrite($f, '  PROJECTION'.PHP_EOL);
      fwrite($f, '    "init='.strtolower($this->projection).'"'.PHP_EOL);
      fwrite($f, '  END # PROJECTION'.PHP_EOL);
    }

    fwrite($f, PHP_EOL);
    fwrite($f, '  WEB'.PHP_EOL);
    if (!empty($this->metadata)) {
      fwrite($f, '    METADATA'.PHP_EOL);
      foreach ($this->metadata as $k => $v) fwrite($f, '      "'.$k.'" "'.$v.'"'.PHP_EOL);
      fwrite($f, '    END # METADATA'.PHP_EOL);
    }
    fwrite($f, '  END # WEB'.PHP_EOL);

    fwrite($f, PHP_EOL);
    fwrite($f, $this->legend->write());

    fwrite($f, PHP_EOL);
    fwrite($f, $this->scalebar->write());

    foreach ($this->_layers as $layer) {
      fwrite($f, PHP_EOL);
      fwrite($f, $layer->write());
    }

    fwrite($f, 'END # MAP'.PHP_EOL);
    fclose($f);
  }

  private function read($mapfile) {
    $map = FALSE; $map_projection = FALSE; $map_outputformat = FALSE; $map_querymap = FALSE; $map_legend = FALSE; $map_scalebar = FALSE; $map_layer = FALSE; $map_web = FALSE; $map_metadata = FALSE;

    $h = fopen($mapfile, 'r');
    while (($_sz = fgets($h, 1024)) !== false) {
      $sz = trim($_sz);

      if (preg_match('/^MAP$/i', $sz)) $map = TRUE;
      else if ($map && preg_match('/^END( # MAP)?$/i', $sz)) $map = FALSE;

      else if ($map && preg_match('/^OUTPUTFORMAT$/i', $sz)) $map_outputformat = TRUE;
      else if ($map && $map_outputformat && preg_match('/^END( # OUTPUTFORMAT)?$/i', $sz)) $map_outputformat = FALSE;
      else if ($map && $map_outputformat) continue;

      else if ($map && preg_match('/^QUERYMAP$/i', $sz)) $map_querymap = TRUE;
      else if ($map && $map_querymap && preg_match('/^END( # QUERYMAP)?$/i', $sz)) $map_querymap = FALSE;
      else if ($map && $map_querymap) continue;

      else if ($map && preg_match('/^PROJECTION$/i', $sz)) $map_projection = TRUE;
      else if ($map && $map_projection && preg_match('/^END( # PROJECTION)?$/i', $sz)) $map_projection = FALSE;
      else if ($map && $map_projection && preg_match('/^"init=(.+)"$/i', $sz, $matches)) $this->projection = $matches[1];

      else if ($map && preg_match('/^LEGEND$/i', $sz)) { $map_legend = TRUE; $legend[] = $sz; }
      else if ($map && $map_legend && preg_match('/^END( # LEGEND)?$/i', $sz)) { $legend[] = $sz; $this->legend = new Legend($legend); $map_legend = FALSE; unset($legend); }
      else if ($map && $map_legend) { $legend[] = $sz; }

      else if ($map && preg_match('/^SCALEBAR$/i', $sz)) { $map_scalebar = TRUE; $scalebar[] = $sz; }
      else if ($map && $map_scalebar && preg_match('/^END( # SCALEBAR)?$/i', $sz)) { $scalebar[] = $sz; $this->scalebar = new Scalebar($scalebar); $map_scalebar = FALSE; unset($scalebar); }
      else if ($map && $map_scalebar) { $scalebar[] = $sz; }

      else if ($map && preg_match('/^LAYER$/i', $sz)) { $map_layer = TRUE; $layer[] = $sz; }
      else if ($map && $map_layer && preg_match('/^END( # LAYER)?$/i', $sz)) { $layer[] = $sz; $this->addLayer(new Layer($layer)); $map_layer = FALSE; unset($layer); }
      else if ($map && $map_layer) { $layer[] = $sz; }

      else if ($map && preg_match('/^WEB$/i', $sz)) { $map_web = TRUE; }
      else if ($map && $map_web && preg_match('/^END( # WEB)?$/i', $sz)) { $map_web = FALSE; }
      else if ($map && $map_web && preg_match('/^METADATA$/i', $sz)) { $map_metadata = TRUE; }
      else if ($map && $map_web && $map_metadata && preg_match('/^END( # METADATA)?$/i', $sz)) { $map_metadata = FALSE; }
      else if ($map && $map_web && $map_metadata && preg_match('/^"(.+)"\s"(.+)"$/i', $sz, $matches)) { $this->metadata[$matches[1]] = $matches[2]; }

      else if ($map && preg_match('/^NAME "(.+)"$/i', $sz, $matches)) $this->name = $matches[1];
      else if ($map && preg_match('/^STATUS (.+)$/i', $sz, $matches)) $this->status = self::convertStatus($matches[1]);
      else if ($map && preg_match('/^EXTENT ([0-9\.]+) ([0-9\.]+) ([0-9\.]+) ([0-9\.]+)$/i', $sz, $matches)) $this->extent = array($matches[1], $matches[2], $matches[3], $matches[4]);
      else if ($map && preg_match('/^FONTSET "(.+)"$/i', $sz, $matches)) $this->fontsetfilename = $matches[1];
      else if ($map && preg_match('/^SYMBOLSET "(.+)"$/i', $sz, $matches)) $this->symbolsetfilename = $matches[1];
      else if ($map && preg_match('/^SIZE ([0-9]+) ([0-9]+)$/i', $sz, $matches)) $this->size = array($matches[1], $matches[2]);

      else if ($map && preg_match('/^UNITS (.+)$/i', $sz, $matches)) $this->units = self::convertUnits($matches[1]);
    }
    fclose($h);
  }

  private function convertStatus($s = NULL) {
    $statuses = array(
      self::STATUS_ON  => 'ON',
      self::STATUS_OFF => 'OFF'
    );

    if (is_null($s)) return $statuses[$this->status];
    else if (is_numeric($s)) return (isset($statuses[$s]) ? $statuses[$s] : FALSE);
    else return array_search($s, $statuses);
  }
  private function convertUnits($u = NULL) {
    $units = array(
      self::UNITS_INCHES        => 'INCHES',
      self::UNITS_FEET          => 'FEET',
      self::UNITS_MILES         => 'MILES',
      self::UNITS_METERS        => 'METERS',
      self::UNITS_KILOMETERS    => 'KILOMETERS',
      self::UNITS_DD            => 'DD',
      self::UNITS_PIXELS        => 'PIXELS',
      self::UNITS_NAUTICALMILES => 'NAUTICALMILES'
    );

    if (is_null($u)) return $units[$this->units];
    else if (is_numeric($u)) return (isset($units[$u]) ? $units[$u] : FALSE);
    else return array_search($u, $units);
  }
}