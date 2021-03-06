<?php
namespace PhpRaffle;

use PhpRaffle\CsvReader;
use PhpRaffle\CsvWriter;
use PhpRaffle\AllDrawnException;
use PhpRaffle\NoMoreAwardsException;

class Raffler
{
    private $attendees      = [];
    private $winners        = [];
    private $noshows        = [];
    private $awards         = [];
    private $csvHeadConfig  = [];

    private $attendeesFilename;
    private $awardsFilename;
    private $winnersFilename;
    private $noshowFilename;

    private $csvReader;
    private $csvWriter;

    public function __construct($options = [])
    {
        $this->attendeesFilename    = isset($options['attendeesFilename']) ? $options['attendeesFilename'] : 'attendees.csv';
        $this->awardsFilename       = isset($options['awardsFilename']) ? $options['awardsFilename'] : 'awards.csv';
        $this->winnersFilename      = isset($options['winnersFilename']) ? $options['winnersFilename'] : 'winners.csv';
        $this->noshowFilename       = isset($options['noshowFilename']) ? $options['noshowFilename'] : 'noshow.csv';
        $this->csvHeadConfig        = isset($options['csvHead'])
            ? $options['csvHead']
            : [
                'id'            => 'Registration ID',
                'email'         => 'Email',
                'name'          => 'Name',
                'first_name'    => null,
                'last_name'     => null,
            ];

        $this->csvReader = isset($options['csvReader']) ? $options['csvReader'] : new CsvReader;
        $this->csvWriter = isset($options['csvWriter']) ? $options['csvWriter'] : new CsvWriter;
    }

    public function init()
    {
        $this->loadWinners();
        $this->loadNoShow();
        $this->loadAttendees();
        $this->loadAwards();
    }

    public function getWinners()
    {
        return $this->winners;
    }

    public function getNoShows()
    {
        return $this->nowshows;
    }

    public function getAllDrawn()
    {
        return $this->winners + $this->noshows;
    }

    public function getAwards()
    {
        return $this->awards;
    }

    public function setAttendees($attendees) {
        $this->attendees = $attendees;
    }

    public function setWinners($winners) {
        if (!empty($this->winners)) {
            throw new GenericException("Cannot set winners more than once in the object's lifetime");
        }

        $this->winners  = $winners;
    }

    public function setNoshows($noshows) {
        if (!empty($this->nowshows)) {
            throw new GenericException("Cannot set noshows more than once in the object's lifetime");
        }

        $this->noshows  = $noshows;
    }

    public function setAwards($awards) {
        // If there are already as many winners as awards (or more) drawn, set awards to an empty array.
        if (count($awards) <= count($this->winners)) {
            $this->awards = [];
            return;
        }

        // Load just the remaining not drawn awards
        $awards = array_slice($awards, count($this->winners));

        $this->awards = $awards;
    }

    private function readCsvFileToArray($filename)
    {
        if (!is_readable($filename)) {
            throw new GenericException("File ({$filename}) not readible!");
        }

        $csvReader = $this->getCsvReader($filename, $this->csvHeadConfig);

        if ($csvReader->openFile()) {
            $arr = $csvReader->readToArray();
            return $arr;
        }

        throw new GenericException("File ({$filename}) could not be processed!");
    }

    private function initEmptyFile($filename) {
        if (!touch($filename)) {
            throw new GenericException("Error creating file $filename");
        }
    }

    private function getCsvReader($filename, $head = null)
    {
        $csvReaderClass = get_class($this->csvReader);
        if (! $csvReaderClass) {
            // TODO: Create a generic base for PhpRaffler exceptions, make NoMore and AllDRawn extend from it
            throw new GenericException('No proper csvReader set');
        }

        $csvReader = new $csvReaderClass($filename);
        if (! $csvReader instanceof CsvReaderInterface) {
            throw new GenericException('Invalid csvReader set');
        }

        if (! empty($head)) {
            $csvReader->setHead($head);
        }

        return $csvReader;
    }

    private function getCsvWriter($filename, $head = null, $mode = 'w')
    {
        $csvWriterClass = get_class($this->csvWriter);
        if (! $csvWriterClass) {
            // TODO: Create a generic base for PhpRaffler exceptions, make NoMore and AllDRawn extend from it
            throw new GenericException('No proper csvWriter set');
        }

        // TODO: Create an interface for csvReader and csvWriter, make them implement it, assert when getting them
        $csvWriter = new $csvWriterClass($filename, $mode);

        if (! empty($head)) {
            $csvWriter->setHead($head);
        }

        return $csvWriter;
    }

    public function getPrimaryKey($line)
    {
        if (isset($this->csvHeadConfig['id']) && isset($line[$this->csvHeadConfig['id']])) {
            return $line[$this->csvHeadConfig['id']];
        }

        if (isset($this->csvHeadConfig['email']) && isset($line[$this->csvHeadConfig['email']])) {
            return $line[$this->csvHeadConfig['email']];
        }

        // Otherwise calculate a hash based on the whole line's content
        return md5(implode($line));
    }

    private function loadWinners()
    {
        if (!file_exists($this->winnersFilename)) {
            $this->initEmptyFile($this->winnersFilename);
        }

        $winners = $this->readCsvFileToArray($this->winnersFilename);

        foreach ($winners as $line) {
            $this->winners[$this->getPrimaryKey($line)] = $line;
        }
    }

    private function loadNoShow()
    {
        if (!file_exists($this->noshowFilename)) {
            $this->initEmptyFile($this->noshowFilename);
        }

        $noshows = $this->readCsvFileToArray($this->noshowFilename);

        foreach ($noshows as $line) {
            $this->noshows[$this->getPrimaryKey($line)] = $line;
        }
    }

    private function loadAttendees()
    {
        $this->attendees = $this->readCsvFileToArray($this->attendeesFilename);
    }

    private function loadAwards()
    {
        $awardsArr = $this->readCsvFileToArray($this->awardsFilename);
        $this->setAwards($awardsArr);
    }

    public function draw(&$award = null)
    {
        $allDrawn = $this->getAllDrawn();
        if (count($allDrawn) >= count($this->attendees)) {
            throw new AllDrawnException("Everybody has been drawn");
        }

        if (empty($this->awards)) {
            throw new NoMoreAwardsException("All awards have been drawn");
        }

        do {
            $drawn  = $this->attendees[array_rand($this->attendees)];
            $key    = $this->getPrimaryKey($drawn);
        } while (isset($allDrawn[$key]));

        $award = current($this->awards);

        return $drawn;
    }

    public function markDrawn($winner)
    {
        $winner['award']    = array_shift($this->awards);
        $this->winners[]    = $winner;

        return $this->writeArrayOffToFile(
            $this->winners,
            $this->winnersFilename
        );
    }

    public function markNoShow($attendee)
    {
        $this->noshows[]    = $attendee;

        return $this->writeArrayOffToFile(
            $this->noshows,
            $this->noshowFilename
        );
    }

    public function writeArrayOffToFile($array, $filename)
    {
        $csvWriter = $this->getCsvWriter($filename);

        if (! $csvWriter->openFile()) {
           throw new GenericException("File ({$filename}) could not be opened for writing!");
        }

        return $csvWriter->writeFromArray($array);
    }

    public function getRandomAttendees($number, $obfuscateEmail = true)
    {
        if ($number <= 0) {
            throw new GenericException("Zero or negative number passed to " . __METHOD__);
        }

        if ($number >= count($this->attendees)) {
            return $obfuscateEmail
                ? $this->obfuscateAttendeesEmailAddresses($this->attendees)
                : $this->attendees;
        }

        $drawnIds           = [];
        $randomAttendees    = [];

        while (count($randomAttendees) < $number) {
            $randId         = array_rand($this->attendees);
            $randomAttendee = $this->attendees[$randId];

            if (! isset($drawnIds[$randId])) {
                $randomAttendees[] = $randomAttendee;
                $drawnIds[$randId] = true;
            }
        }

        return $obfuscateEmail
                ? $this->obfuscateAttendeesEmailAddresses($randomAttendees)
                : $randomAttendees;
    }

    public function obfuscateAttendeesEmailAddresses($attendees)
    {
        foreach ($attendees as &$attendee) {
            if (isset($attendee['Email'])) {
                $attendee['Email'] = $this->obfuscateEmailAddress($attendee['Email']);
            }
        }

        return $attendees;
    }

    public function obfuscateEmailAddress($email)
    {
        $parts = explode('@', $email);
        $parts[0] = str_pad(substr($parts[0], 0, 1), strlen($parts[0]) - 2, '*') . substr($parts[0], -1);

        return $parts[0] . '@' . $parts[1];
    }
}
