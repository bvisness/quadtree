<?php

namespace Quadtree;

use Geometry\Rectangle;

class Quadtree
{
    const MAX_OBJECTS = 10;
    const MAX_LEVELS = 5;

    const LOWER_LEFT = 0;
    const LOWER_RIGHT = 1;
    const UPPER_LEFT = 2;
    const UPPER_RIGHT = 3;

    private $level;
    private $objects;
    private $bounds;
    private $nodes;

    public function __construct(Rectangle $bounds, $level = 0)
    {
        $this->level = $level;
        $this->objects = [];
        $this->bounds = $bounds->normalized();
        $this->nodes = [];
    }

    private function isSplit()
    {
        return isset($this->nodes[0]);
    }

    public function clear()
    {
        $this->objects = [];

        foreach ($this->nodes as $node) {
            $node->clear();
        }
        $this->nodes = [];
    }

    private function split()
    {
        list($midX, $midY) = $this->bounds->center();
        $x1 = $this->bounds->x1;
        $y1 = $this->bounds->y1;
        $x2 = $this->bounds->x2;
        $y2 = $this->bounds->y2;
        
        $this->nodes = [];
        $this->nodes[self::LOWER_LEFT] = new Quadtree(new Rectangle($x1, $y1, $midX, $midY), $this->level + 1);
        $this->nodes[self::LOWER_RIGHT] = new Quadtree(new Rectangle($midX, $y1, $x2, $midY), $this->level + 1);
        $this->nodes[self::UPPER_LEFT] = new Quadtree(new Rectangle($x1, $midY, $midX, $y2), $this->level + 1);
        $this->nodes[self::UPPER_RIGHT] = new Quadtree(new Rectangle($midX, $midY, $x2, $y2), $this->level + 1);
    }

    private function getIndex(Rectangle $rect)
    {
        if ($this->nodes[self::LOWER_LEFT]->bounds->contains($rect)) {
            return self::LOWER_LEFT;
        } elseif ($this->nodes[self::LOWER_RIGHT]->bounds->contains($rect)) {
            return self::LOWER_RIGHT;
        } elseif ($this->nodes[self::UPPER_LEFT]->bounds->contains($rect)) {
            return self::UPPER_LEFT;
        } elseif ($this->nodes[self::UPPER_RIGHT]->bounds->contains($rect)) {
            return self::UPPER_RIGHT;
        }

        // Was not fully contained by any child node
        return -1;
    }

    public function insert(Rectangle $rect)
    {
        if ($this->isSplit()) {
            $index = $this->getIndex($rect);

            if ($index !== -1) {
                $this->nodes[$index]->insert($rect);
                return;
            }
        }

        $this->objects[] = $rect;

        if (count($this->objects) > self::MAX_OBJECTS && $this->level < self::MAX_LEVELS) {
            // We have enough objects to split
            if (!$this->isSplit()) {
                $this->split();
            }

            // Insert objects into sub-nodes if possible
            foreach ($this->objects as $key => $object) {
                $index = $this->getIndex($object);
                if ($index !== -1) {
                    $this->nodes[$index]->insert($object);
                    unset($this->objects[$key]);
                }
            }
        }
    }

    public function retrieve(Rectangle $rect)
    {
        $returnObjects = [];

        // Always include the objects from this node before going deeper
        $returnObjects = array_merge($returnObjects, $this->objects);

        // If we have no child nodes, we're done
        if (!$this->isSplit()) {
            return $returnObjects;
        }

        $index = $this->getIndex($rect);
        if ($index !== -1) {
            // Object is completely contained by child node
            $returnObjects = array_merge($returnObjects, $this->nodes[$index]->retrieve($rect));
            return $returnObjects;
        }

        // Check intersections with each quadrant
        for ($i = self::LOWER_LEFT; $i < 4; $i++) {
            if (!$this->nodes[$i]->bounds->intersects($rect)) {
                continue;
            }

            $returnObjects = array_merge($returnObjects, $this->nodes[$i]->retrieve($rect));
        }

        return $returnObjects;
    }
}
