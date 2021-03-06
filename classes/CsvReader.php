<?php
namespace PhpRaffle;

use PhpRaffle\CsvReaderInterface;

class CsvReader implements CsvReaderInterface
{
    private $fname;
    private $fh;
    private $head;

    public $delimiter    = ',';
    public $enclosure    = '"';
    public $escape       = '\\';  //a backslash - default - not used

    public function __construct($fname = null)
    {
        $this->setFName($fname);
    }

    public function setFName($fname)
    {
        $this->fname = $fname;
    }

    public function setHead($head)
    {
        $this->head = $head;
    }

    public function readToArray()
    {
        if (!$this->openFile($this->fname)) {
            return false;
        }

        $arr = array();
        $i = 0;
        while ($linearr = $this->readLine()) {
            $arr[$i++] = $linearr;
        }

        $this->closeFile();
        return $arr;
    }

    public function readLine()
    {
        $linearr = fgetcsv($this->fh, 0, $this->delimiter, $this->enclosure);

        if ($linearr && !is_null($this->head)) {
            //replace numeric indices with ones from head, if any

            foreach ($linearr as $key => $value) {
                $linearr[$this->head[$key]] = $value;
                unset($linearr[$key]);
            }
        }

        return $linearr;
    }

    public function openFile()
    {
        $this->fh = fopen($this->fname, 'r');
        return (bool) $this->fh;
    }

    public function closeFile()
    {
        return fclose($this->fh);
    }
}
