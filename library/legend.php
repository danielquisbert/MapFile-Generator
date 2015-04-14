<?php
namespace MapFile;

require_once('label.php');

class Legend {
  public $status = self::STATUS_OFF;

  public $label;

  const STATUS_ON = 1;
  const STATUS_OFF = 0;

  public function __construct($legend = NULL) {
    if (!is_null($legend)) $this->read($legend);

    if (is_null($this->label)) $this->label = new Label();
  }

  public function write() {
    $legend  = '  LEGEND'.PHP_EOL;
    $legend .= '    STATUS '.$this->convertStatus().PHP_EOL;
    $legend .= $this->label->write(2);
    $legend .= '  END # LEGEND'.PHP_EOL;

    return $legend;
  }

  private function read($array) {
    $legend = FALSE; $legend_label = FALSE;

    foreach ($array as $_sz) {
      $sz = trim($_sz);

      if (preg_match('/^LEGEND$/i', $sz)) $legend = TRUE;
      else if ($legend && preg_match('/^END( # LEGEND)?$/i', $sz)) $legend = FALSE;

      else if ($legend && preg_match('/^LABEL$/i', $sz)) { $legend_label = TRUE; $label[] = $sz; }
      else if ($legend && $legend_label && preg_match('/^END( # LABEL)?$/i', $sz)) { $label[] = $sz; $this->label = new Label($label); $legend_label = FALSE; unset($label); }
      else if ($legend && $legend_label) { $label[] = $sz; }

      else if ($legend && preg_match('/^STATUS (.+)$/i', $sz, $matches)) $this->status = self::convertStatus($matches[1]);
    }
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
}