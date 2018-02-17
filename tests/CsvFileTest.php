<?php

namespace Keboola\Csv\Tests;

use Keboola\Csv\CsvFile;
use League\Csv\EncloseField;
use League\Csv\Reader;
use League\Csv\RFC4180Field;
use League\Csv\Statement;
use League\Csv\Writer;
use PHPUnit\Framework\TestCase;
use SplFileObject;

class CsvFileTest extends TestCase
{
    public function testExistingFileShouldBeCreated()
    {
        self::markTestSkipped();
        self::assertInstanceOf(CsvFile::class, new CsvFile(__DIR__ . '/data/test-input.csv'));
    }

    public function testAccessors()
    {
        self::markTestSkipped();
        $csvFile = new CsvFile(__DIR__ . '/data/test-input.csv');
        self::assertEquals('test-input.csv', $csvFile->getBasename());
        self::assertEquals("\"", $csvFile->getEnclosure());
        self::assertEquals("", $csvFile->getEscapedBy());
        self::assertEquals(",", $csvFile->getDelimiter());
    }

    public function testColumnsCount()
    {
        /** @var Reader $reader */
        $reader = Reader::createFromPath(__DIR__ . '/data/test-input.csv');
        $reader->setHeaderOffset(0);

        self::assertEquals(9, count($reader->getHeader()));
    }

    /**
     * @dataProvider validCsvFiles
     * @param string $fileName
     * @param string $delimiter
     */
    public function testRead($fileName, $delimiter)
    {
        /** @var Reader $reader */
        $reader = Reader::createFromPath(__DIR__ . '/data/' . $fileName);
        $reader->setHeaderOffset(0);
        $reader->setDelimiter($delimiter);
        $reader->setEnclosure('"');

        $expected = [
            "id",
            "idAccount",
            "date",
            "totalFollowers",
            "followers",
            "totalStatuses",
            "statuses",
            "kloutScore",
            "timestamp",
        ];
        self::assertEquals($expected, $reader->getHeader());
    }

    public function validCsvFiles()
    {
        return [
            ['test-input.csv', ','],
            ['test-input.win.csv', ','],
            ['test-input.tabs.csv', "\t"],
            ['test-input.tabs.csv', "	"],
        ];
    }

    public function testParse()
    {
        /** @var Reader $reader */
        $reader = Reader::createFromPath(__DIR__ . '/data/escaping.csv');
        $reader->setDelimiter(",");
        $reader->setEnclosure('"');
        RFC4180Field::addTo($reader);

        $rows = [];
        foreach ($reader->getRecords() as $row) {
            $rows[] = $row;
        }

        $expected = [
            [
                'col1', 'col2',
            ],
            [
                'line without enclosure', 'second column',
            ],
            [
                'enclosure " in column', 'hello \\',
            ],
            [
                'line with enclosure', 'second column',
            ],
            [
                'column with enclosure ", and comma inside text', 'second column enclosure in text "',
            ],
            [
                "columns with\nnew line", "columns with\ttab",
            ],
            [
                "Columns with WINDOWS\r\nnew line", "second",
            ],
            [
                'column with \n \t \\\\', 'second col',
            ],
        ];

        self::assertEquals($expected, $rows);
    }


    public function testEmptyHeader()
    {
        /** @var Reader $reader */
        $reader = Reader::createFromPath(__DIR__ . '/data/test-input.empty.csv');
        $reader->setDelimiter(",");
        $reader->setEnclosure('"');

        self::assertEquals([], $reader->getHeader());
    }

    public function testInitInvalidFileShouldNotThrowException()
    {
        self::markTestSkipped();
        try {
            new CsvFile(__DIR__ . '/data/dafadfsafd.csv');
        } catch (\Exception $e) {
            self::fail('Exception should not be thrown');
        }
    }

    /**
     * @param string $file
     * @param string $lineBreak
     * @param string $lineBreakAsText
     * @dataProvider validLineBreaksData
     */
    public function testLineEndingsDetection($file, $lineBreak, $lineBreakAsText)
    {
        self::markTestSkipped();
        // https://github.com/thephpleague/csv#configuration
        $csvFile = new CsvFile(__DIR__ . '/data/' . $file);
        self::assertEquals($lineBreak, $csvFile->getLineBreak());
        self::assertEquals($lineBreakAsText, $csvFile->getLineBreakAsText());
    }

    public function validLineBreaksData()
    {
        return [
            ['test-input.csv', "\n", '\n'],
            ['test-input.win.csv', "\r\n", '\r\n'],
            ['escaping.csv', "\n", '\n'],
            ['just-header.csv', "\n", '\n'], // default
        ];
    }

    /**
     * @expectedException \Keboola\Csv\InvalidArgumentException
     * @dataProvider invalidLineBreaksData
     * @param string $file
     */
    public function testInvalidLineBreak($file)
    {
        self::markTestSkipped();
        $csvFile = new CsvFile(__DIR__ . '/data/' . $file);
        $csvFile->validateLineBreak();
    }

    public function invalidLineBreaksData()
    {
        return [
            ['test-input.mac.csv'],
        ];
    }

    public function testWrite()
    {
        $fileName = __DIR__ . '/data/_out.csv';
        if (file_exists($fileName)) {
            unlink($fileName);
        }

        $writer = Writer::createFromPath($fileName, 'w+');
        $writer->setNewline("\n");
        $writer->setEnclosure('"');
        RFC4180Field::addTo($writer);
        //EncloseField::addTo($writer, "\n\r\t"); ????

        $rows = [
            [
                'col1', 'col2',
            ],
            [
                'line without enclosure', 'second column',
            ],
            [
                'enclosure " in column', 'hello \\',
            ],
            [
                'line with enclosure', 'second column',
            ],
            [
                'column with enclosure ", and comma inside text', 'second column enclosure in text "',
            ],
            [
                "columns with\nnew line", "columns with\ttab",
            ],
            [
                'column with \n \t \\\\', 'second col',
            ]
        ];

        foreach ($rows as $row) {
            $writer->insertOne($row);
        }
        self::assertFileEquals(__DIR__ . '/data/sample_out_1.csv', $fileName);
    }

    public function testIterator()
    {
        /** @var Reader $reader */
        $reader = Reader::createFromPath(__DIR__ . '/data/test-input.csv');
        $records = $reader->getRecords();

        $expected = [
            "id",
            "idAccount",
            "date",
            "totalFollowers",
            "followers",
            "totalStatuses",
            "statuses",
            "kloutScore",
            "timestamp",
        ];

        // header line
        $records->rewind();
        self::assertEquals($expected, $records->current());

        // first line
        $records->next();
        self::assertTrue($records->valid());

        // second line
        $records->next();
        self::assertTrue($records->valid());

        // file end
        $records->next();
        self::assertFalse($records->valid());
    }

    public function testSkipsHeaders()
    {
        $fileName = __DIR__ . '/data/simple.csv';
        /** @var Reader $reader */
        $reader = Reader::createFromPath($fileName);
        $stmt = (new Statement())
            ->offset(1);
        $records = $stmt->process($reader);

        self::assertEquals([
            ['15', '0'],
            ['18', '0'],
            ['19', '0'],
        ], iterator_to_array($records));
    }

    public function testSkipNoLines()
    {
        $reader = Reader::createFromPath(__DIR__ . '/data/simple.csv');
        $records = (new Statement())->process($reader);

        self::assertEquals([
            ['id', 'isImported'],
            ['15', '0'],
            ['18', '0'],
            ['19', '0'],
        ], iterator_to_array($records));
    }

    public function testSkipsMultipleLines()
    {
        $fileName = __DIR__ . '/data/simple.csv';
        /** @var Reader $reader */
        $reader = Reader::createFromPath($fileName);
        $stmt = (new Statement())
            ->offset(3);
        $records = $stmt->process($reader);

        self::assertEquals([
            ['19', '0'],
        ], iterator_to_array($records));
    }

    public function testSkipsOverflow()
    {
        $fileName = __DIR__ . '/data/simple.csv';
        /** @var Reader $reader */
        $reader = Reader::createFromPath($fileName);
        $stmt = (new Statement())
            ->offset(100);
        $records = $stmt->process($reader);
        self::assertEquals([], iterator_to_array($records));
    }
}
