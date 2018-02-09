<?php

namespace PunktDe\Archivist\Tests\Functional;

/*
 * This file is part of the PunktDe.Archivist package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeTemplate;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Tests\Functional\AbstractNodeTest;
use Neos\Eel\Exception;
use Neos\Eel\FlowQuery\FlowQuery;

class ArchivistTest extends AbstractNodeTest
{

    /**
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    public function setUp()
    {
        parent::setUp();
        $this->nodeTypeManager = $this->objectManager->get(NodeTypeManager::class);
    }

    /**
     * @test
     */
    public function nodeStructureIsAvailable()
    {
        $this->assertEquals('Neos.ContentRepository.Testing:Page', $this->node->getNodeType()->getName());
    }

    /**
     * @test
     */
    public function simpleCreateNode()
    {
        $newNode = $this->createNode('trigger-node', ['title' => 'New Article', 'date' => new \DateTime('2018-01-19')]);

        // The hierarchy is created
        $lvl1ChildNodes = $this->node->getChildNodes('PunktDe.Archivist.HierarchyNode');
        $this->assertCount(1, $lvl1ChildNodes);
        $lvl1 =$lvl1ChildNodes[0];
        $this->assertInstanceOf(NodeInterface::class, $lvl1);
        $this->assertEquals('2018', $lvl1->getProperty('title'));

        $lvl2 = $lvl1->getChildNodes('PunktDe.Archivist.HierarchyNode')[0];
        $this->assertInstanceOf(NodeInterface::class, $lvl2);
        $this->assertEquals('01', $lvl2->getProperty('title'));
        $this->assertEquals($this->nodeContextPath . '/2018/1', $lvl2->getPath());

        // The node is sorted in the hierarchy
        $this->assertEquals($this->nodeContextPath . '/2018/1/trigger-node', $newNode->getPath());
    }


    /**
     * @test
     */
    public function doNotSortWhenConditionIsNotMet()
    {
        $triggerNode = $this->createNode('trigger-node', ['title' => 'New Article']);
        $this->assertCount(0, $this->node->getChildNodes('PunktDe.Archivist.HierarchyNode'));
        
        $triggerNode->setProperty('date', new \DateTime('2018-01-19'));
        $this->assertEquals($this->nodeContextPath . '/2018/1/trigger-node', $triggerNode->getPath());
    }

    /**
     * @test
     */
    public function hierarchyIsNotCreatedTwice()
    {
        $this->createNode('trigger-node1', ['title' => 'New Article', 'date' => new \DateTime('2018-01-19')]);
        $this->createNode('trigger-node2', ['title' => 'New Article', 'date' => new \DateTime('2018-01-19')]);

        $this->assertCount(1, $this->node->getChildNodes('PunktDe.Archivist.HierarchyNode'));
    }

    /**
     * @test
     */
    public function hierarchyNodesAreSortedCorrectlyWithSimpleProperty()
    {
        $this->createNode('trigger-node1', ['title' => 'New Article', 'date' => new \DateTime('2018-01-20')]);
        $this->createNode('trigger-node2', ['title' => 'New Article', 'date' => new \DateTime('2017-01-19')]);

        $yearNodes = $this->node->getChildNodes('PunktDe.Archivist.HierarchyNode');
        $this->assertEquals('2017', $yearNodes[0]->getProperty('title'));
        $this->assertEquals('2018', $yearNodes[1]->getProperty('title'));
    }

    /**
     * @test
     */
    public function hierarchyNodesAreSortedCorrectlyWithEelExpression()
    {
        $this->createNode('trigger-node1', ['title' => 'New Article', 'date' => new \DateTime('2018-02-20')]);
        $this->createNode('trigger-node2', ['title' => 'New Article', 'date' => new \DateTime('2016-01-19')]);

        $monthNodes = (new FlowQuery([$this->node]))->children('[instanceof PunktDe.Archivist.HierarchyNode]')->children('[instanceof PunktDe.Archivist.HierarchyNode]')->get();
        $this->assertEquals('1', $monthNodes[0]->getProperty('title'));
        $this->assertEquals('2', $monthNodes[1]->getProperty('title'));
    }

    /**
     * @test
     */
    public function createdNodesAreSortedCorrectly()
    {
        $this->createNode('trigger-node2', ['title' => 'Node 2', 'date' => new \DateTime('2018-01-19')]);
        $this->createNode('trigger-node1', ['title' => 'Node 1', 'date' => new \DateTime('2018-01-19')]);

        $triggerNodes = (new FlowQuery([$this->node]))->find('[instanceof PunktDe.Archivist.TriggerNode]')->get();
        $this->assertCount(2, $triggerNodes);

        $this->assertEquals('Node 1', $triggerNodes[0]->getProperty('title'));
        $this->assertEquals('Node 2', $triggerNodes[1]->getProperty('title'));
    }

    /**
     * @test
     */
    public function nodesAreSortedIfHierarchyAlreadyExist()
    {
        $triggerNode2 = $this->createNode('trigger-node2', ['title' => 'Node 2', 'date' => new \DateTime('2018-01-19')]);
        $triggerNode1 = $this->createNode('trigger-node1', ['title' => 'Node 1', 'date' => new \DateTime('2018-01-19')]);

        $yearNode = (new FlowQuery([$this->node]))->children('[instanceof PunktDe.Archivist.HierarchyNode]')->get(0);
        $this->assertEquals('2018', $yearNode->getProperty('title'));

        $monthNode = (new FlowQuery([$yearNode]))->children('[instanceof PunktDe.Archivist.HierarchyNode]')->get(0);
        $this->assertEquals('1', $monthNode->getProperty('title'));

        $childNodes = $monthNode->getChildNodes();

        $this->assertCount(2, $childNodes);
        $this->assertSame($triggerNode1, $childNodes[0]);
        $this->assertSame($triggerNode2, $childNodes[1]);
    }

    /**
     * @test
     */
    public function changedPropertyTriggersNodeReSorting()
    {
        $triggerNode = $this->createNode('trigger-node', ['title' => 'Node 1', 'date' => new \DateTime('2018-01-19')]);
        $expectedPath = $this->node->getPath() . '/2018/1/trigger-node';
        $this->assertEquals($expectedPath, $triggerNode->getPath());

        $triggerNode->setProperty('date', new \DateTime('2018-02-04'));
        $expectedPath = $this->node->getPath() . '/2018/2/trigger-node';
        $this->assertEquals($expectedPath, $triggerNode->getPath());
    }

    /**
     * @test
     */
    public function documentNodeIsSortedByTriggeringContentNode()
    {
        $affectedDocumentNode = $this->createNode('affected-node', ['title' => 'theTitle'], 'Neos.ContentRepository.Testing:Document');
        $triggerNode = $affectedDocumentNode->createNode('trigger-node', $this->nodeTypeManager->getNodeType('PunktDe.Archivist.TriggerContentNode'));
        $triggerNode->setProperty('title', 'ABC');
        $this->assertEquals('A', $affectedDocumentNode->getParent()->getProperty('title'));

        $triggerNode->setProperty('title', 'BCD');
        $this->assertEquals('B', $affectedDocumentNode->getParent()->getProperty('title'));
    }

    /**
     * @param string $nodeName
     * @param array $properties
     * @param string $triggerNodeType
     * @return \Neos\ContentRepository\Domain\Model\NodeInterface
     */
    protected function createNode($nodeName = 'trigger-node', array $properties = [], $triggerNodeType = 'PunktDe.Archivist.TriggerNode')
    {
        $triggerNodeType = $this->nodeTypeManager->getNodeType($triggerNodeType);

        $triggerNodeTemplate = new NodeTemplate();
        $triggerNodeTemplate->setNodeType($triggerNodeType);

        foreach ($properties as $key => $property) {
            $triggerNodeTemplate->setProperty($key, $property);
        }

        return $this->node->createNodeFromTemplate($triggerNodeTemplate, $nodeName);
    }
}
