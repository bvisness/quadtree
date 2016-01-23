<?php

namespace QuadtreeTests;

use Geometry\Rectangle;
use Quadtree\Quadtree;

class QuadtreeTest extends \PHPUnit_Framework_TestCase
{
    private $quadClass;

    public function __construct()
    {
        $this->quadClass = new \ReflectionClass('Quadtree\Quadtree');
    }

    private function getPropertyValue($name, Quadtree $quad)
    {
        $prop = $this->quadClass->getProperty($name);
        $prop->setAccessible(true);
        return $prop->getValue($quad);
    }

    private function invokeMethod($name, Quadtree $quad, $args = [])
    {
        $method = $this->quadClass->getMethod($name);
        $method->setAccessible(true);
        return $method->invokeArgs($quad, $args);
    }

    private function insertRectangles(Quadtree $quad)
    {
        $x = $this->getPropertyValue('bounds', $quad)->x1;
        $y = $this->getPropertyValue('bounds', $quad)->y1;
        $width = $this->getPropertyValue('bounds', $quad)->width();
        $height = $this->getPropertyValue('bounds', $quad)->height();

        // Insert into the lower left and upper right quadrants (and one too big for a quadrant)
        for ($i = 0; $i < Quadtree::MAX_OBJECTS / 2; $i++) {
            $quad->insert(new Rectangle($x, $y, $x + 1, $y + 1));
        }
        for ($i = 0; $i < Quadtree::MAX_OBJECTS / 2; $i++) {
            $quad->insert(new Rectangle($x + $width, $y + $height, $x + $width - 1, $y + $height - 1));
        }
        $quad->insert(new Rectangle($x + 1, $y + 1, $x + $width - 1, $y + $height - 1));
    }

    public function testGetIndex()
    {
        $quad = new Quadtree(new Rectangle(0, 0, 16, 16));
        $this->insertRectangles($quad); // Just to split it

        // Test nicely inside quadrant
        $this->assertSame(Quadtree::LOWER_LEFT, $this->invokeMethod('getIndex', $quad, [new Rectangle(1, 1, 7, 7)]));
        $this->assertSame(Quadtree::LOWER_RIGHT, $this->invokeMethod('getIndex', $quad, [new Rectangle(9, 1, 15, 7)]));
        $this->assertSame(Quadtree::UPPER_LEFT, $this->invokeMethod('getIndex', $quad, [new Rectangle(1, 9, 7, 15)]));
        $this->assertSame(Quadtree::UPPER_RIGHT, $this->invokeMethod('getIndex', $quad, [new Rectangle(9, 9, 15, 15)]));

        // Test full size of quadrant
        $this->assertSame(Quadtree::LOWER_LEFT, $this->invokeMethod('getIndex', $quad, [new Rectangle(0, 0, 8, 8)]));
        $this->assertSame(Quadtree::LOWER_RIGHT, $this->invokeMethod('getIndex', $quad, [new Rectangle(8, 0, 16, 8)]));
        $this->assertSame(Quadtree::UPPER_LEFT, $this->invokeMethod('getIndex', $quad, [new Rectangle(0, 8, 8, 16)]));
        $this->assertSame(Quadtree::UPPER_RIGHT, $this->invokeMethod('getIndex', $quad, [new Rectangle(8, 8, 16, 16)]));

        // Test too big for quadrant
        $this->assertSame(-1, $this->invokeMethod('getIndex', $quad, [new Rectangle(1, 1, 15, 15)]));

        // Test outside of quadtree
        $this->assertSame(-1, $this->invokeMethod('getIndex', $quad, [new Rectangle(-4, 0, -1, 3)]));
    }

    public function testInsertAndSplit()
    {
        $originalWidth = 16;
        $originalHeight = 8;
        $splitWidth = $originalWidth / 2;
        $splitHeight = $originalHeight / 2;

        $quad = new Quadtree(new Rectangle(0, 0, $originalWidth, $originalHeight));

        $this->assertFalse($this->invokeMethod('isSplit', $quad));

        $this->insertRectangles($quad);

        $this->assertTrue($this->invokeMethod('isSplit', $quad));

        // Test dimensions of each node
        $nodes = $this->getPropertyValue('nodes', $quad);
        $this->assertSame($splitWidth, $this->getPropertyValue('bounds', $nodes[Quadtree::LOWER_LEFT])->width());
        $this->assertSame($splitHeight, $this->getPropertyValue('bounds', $nodes[Quadtree::LOWER_LEFT])->height());
        $this->assertSame($splitWidth, $this->getPropertyValue('bounds', $nodes[Quadtree::LOWER_RIGHT])->width());
        $this->assertSame($splitHeight, $this->getPropertyValue('bounds', $nodes[Quadtree::LOWER_RIGHT])->height());
        $this->assertSame($splitWidth, $this->getPropertyValue('bounds', $nodes[Quadtree::UPPER_LEFT])->width());
        $this->assertSame($splitHeight, $this->getPropertyValue('bounds', $nodes[Quadtree::UPPER_LEFT])->height());
        $this->assertSame($splitWidth, $this->getPropertyValue('bounds', $nodes[Quadtree::UPPER_RIGHT])->width());
        $this->assertSame($splitHeight, $this->getPropertyValue('bounds', $nodes[Quadtree::UPPER_RIGHT])->height());

        // Test positions of each node
        $this->assertSame(0, $this->getPropertyValue('bounds', $nodes[Quadtree::LOWER_LEFT])->x1);
        $this->assertSame(0, $this->getPropertyValue('bounds', $nodes[Quadtree::LOWER_LEFT])->y1);
        $this->assertSame($splitWidth, $this->getPropertyValue('bounds', $nodes[Quadtree::LOWER_RIGHT])->x1);
        $this->assertSame(0, $this->getPropertyValue('bounds', $nodes[Quadtree::LOWER_RIGHT])->y1);
        $this->assertSame(0, $this->getPropertyValue('bounds', $nodes[Quadtree::UPPER_LEFT])->x1);
        $this->assertSame($splitHeight, $this->getPropertyValue('bounds', $nodes[Quadtree::UPPER_LEFT])->y1);
        $this->assertSame($splitWidth, $this->getPropertyValue('bounds', $nodes[Quadtree::UPPER_RIGHT])->x1);
        $this->assertSame($splitHeight, $this->getPropertyValue('bounds', $nodes[Quadtree::UPPER_RIGHT])->y1);

        // Test number of objects in each node
        $currentObjects = $this->getPropertyValue('objects', $quad);
        $lowerLeftObjects = $this->getPropertyValue('objects', $nodes[Quadtree::LOWER_LEFT]);
        $lowerRightObjects = $this->getPropertyValue('objects', $nodes[Quadtree::LOWER_RIGHT]);
        $upperLeftObjects = $this->getPropertyValue('objects', $nodes[Quadtree::UPPER_LEFT]);
        $upperRightObjects = $this->getPropertyValue('objects', $nodes[Quadtree::UPPER_RIGHT]);
        $this->assertSame(1, count($currentObjects));
        $this->assertSame((int)(Quadtree::MAX_OBJECTS / 2), count($lowerLeftObjects));
        $this->assertSame(0, count($lowerRightObjects));
        $this->assertSame(0, count($upperLeftObjects));
        $this->assertSame((int)(Quadtree::MAX_OBJECTS / 2), count($upperRightObjects));

        // Test level of each node
        $this->assertSame(0, $this->getPropertyValue('level', $quad));
        $this->assertSame(1, $this->getPropertyValue('level', $nodes[Quadtree::LOWER_LEFT]));
        $this->assertSame(1, $this->getPropertyValue('level', $nodes[Quadtree::LOWER_RIGHT]));
        $this->assertSame(1, $this->getPropertyValue('level', $nodes[Quadtree::UPPER_LEFT]));
        $this->assertSame(1, $this->getPropertyValue('level', $nodes[Quadtree::UPPER_RIGHT]));
    }

    public function testClear()
    {
        $quad = new Quadtree(new Rectangle(0, 0, 16, 16));
        $this->insertRectangles($quad);

        $quad->clear();

        $this->assertSame(0, count($this->getPropertyValue('objects', $quad)));
        $this->assertSame(0, count($this->getPropertyValue('nodes', $quad)));
    }

    public function testRetrieve()
    {
        $quad = new Quadtree(new Rectangle(0, 0, 16, 16));
        $this->insertRectangles($quad);

        $returned = $quad->retrieve(new Rectangle(1, 1, 7, 7));
        $this->assertSame((int)(Quadtree::MAX_OBJECTS / 2) + 1, count($returned));

        $returned = $quad->retrieve(new Rectangle(9, 9, 15, 15));
        $this->assertSame((int)(Quadtree::MAX_OBJECTS / 2) + 1, count($returned));

        $returned = $quad->retrieve(new Rectangle(7, 7, 9, 9));
        $this->assertSame(Quadtree::MAX_OBJECTS + 1, count($returned));

        // Insert more to split the upper right quadrant
        $nodes = $this->getPropertyValue('nodes', $quad);
        $this->insertRectangles($nodes[Quadtree::UPPER_RIGHT]);

        $returned = $quad->retrieve(new Rectangle(9, 9, 10, 10));
        $this->assertSame(1, count($this->getPropertyValue('objects', $quad)));
        $this->assertSame(1, count($this->getPropertyValue('objects', $nodes[Quadtree::UPPER_RIGHT])));
        $this->assertSame((int)(Quadtree::MAX_OBJECTS / 2) + 2, count($returned));

        $returned = $quad->retrieve(new Rectangle(10, 2, 11, 10));
        $this->assertSame(7, count($returned));

        $returned = $quad->retrieve(new Rectangle(17, 17, 18, 18)); // outside quadtree
        $this->assertSame(1, count($returned));
    }
}
