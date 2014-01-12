<?php

/*
 * This file is part of the FixtureDumper library.
 *
 * (c) Martin Parsiegla <martin.parsiegla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sp\FixtureDumper;

use Doctrine\Common\Persistence\ObjectManager;
use Sp\FixtureDumper\Generator\AbstractGenerator;
use Symfony\Component\Filesystem\Filesystem;
use PhpCollection\MapInterface;
use Sp\FixtureDumper\Converter\Handler\HandlerRegistryInterface;
use Sp\FixtureDumper\Converter\DefaultNavigator;

/**
 * General class for dumping fixtures.
 *
 * @author Martin Parsiegla <martin.parsiegla@gmail.com>
 */
abstract class AbstractDumper
{

    /**
     * @var \PhpCollection\MapInterface
     */
    protected $generators;

    /**
     * @var \Doctrine\Common\Persistence\ObjectManager
     */
    protected $objectManager;

    /**
     * @var Converter\Handler\HandlerRegistryInterface
     */
    protected $handlerRegistry;

    /**
     * @var bool
     */
    protected $dumpMultipleFiles;

    /**
     * @var array
     */
    protected $dumpNamespaces;

    /**
     * @var array
     */
    protected $blackList;

    /**
     * @var array
     */
    protected $whiteList;

    /**
     * Construct.
     *
     * @param \Doctrine\Common\Persistence\ObjectManager $objectManager
     * @param Converter\Handler\HandlerRegistryInterface $handlerRegistry
     * @param \PhpCollection\MapInterface                $generators
     *
     * @internal param \Sp\FixtureDumper\Generator\AbstractGenerator $generator
     */
    public function __construct(ObjectManager $objectManager, HandlerRegistryInterface $handlerRegistry, MapInterface $generators)
    {
        $this->objectManager = $objectManager;
        $this->handlerRegistry = $handlerRegistry;
        $this->generators = $generators;
        $this->dumpMultipleFiles = true;
    }

    /**
     * @param       $path
     * @param       $format
     * @param array $options
     */
    public function dump($path, $format, array $options = array())
    {
        $metadata = $this->getDumpOrder($this->getAllMetadata());
        $generator = $this->generators->get($format)->get();

        $generator->setNavigator(new DefaultNavigator($this->handlerRegistry, $format));
        $generator->setManager($this->objectManager);

        $fixtures = array();
        foreach ($metadata as $data) {

            if ($this->skipEntity($data)) {
                continue;
            }

            $fixture = $generator->generate($data, null, $options);
            if ($this->dumpMultipleFiles) {
                $fileName = $generator->createFileName($data, true);
                $this->writeFixture($generator, $fixture, $path, $fileName);
            } else {
                $fileName = $generator->createFileName($data, false);
            }

            $fixtures[] = $fixture;
        }

        if (!$this->dumpMultipleFiles && count($fixtures) != 0) {
            $fixture = implode("\n\n", $fixtures);

            $this->writeFixture($generator, $fixture, $path, $fileName);
        }
    }

    /**
     * @param bool $dumpMultipleFiles
     */
    public function setDumpMultipleFiles($dumpMultipleFiles)
    {
        $this->dumpMultipleFiles = $dumpMultipleFiles;
    }

    /**
     * @return bool
     */
    public function shouldDumpMultipleFiles()
    {
        return $this->dumpMultipleFiles;
    }

    /**
     * add an entity namespace to list of namespaces to dump
     *
     * @param string $ns
     */
    public function addNamespace($ns)
    {
        if (!is_array($this->dumpNamespaces)) {
            $this->dumpNamespaces = array();
        }
        array_push($this->dumpNamespaces, $ns);
    }

    /**
     * Define all namespaces to dump
     * @param array $namespaces
     */
    public function setNamespaces($namespaces)
    {
        if (is_array($namespaces)) {
            $this->dumpNamespaces = $namespaces;
        }
    }

    /**
     * add an entity or list of entities to whitelist dump
     *
     * @param [type] $wl [description]
     */
    public function addToWhitelist($wl)
    {
        if (!is_array($this->whiteList)) {
            $this->whiteList = array();
        }

        if (is_array($wl)) {
            $this->whiteList = array_merge($this->whiteList, $wl);
        } else {
            array_push($this->whiteList, $wl);
        }
    }

    public function addToBlacklist($bl)
    {
        if (!is_array($this->blackList)) {
            $this->blackList = array();
        }

        if (is_array($bl)) {
            $this->blackList = array_merge($this->blackList, $bl);
        } else {
            array_push($this->blackList, $bl);
        }
    }

    /**
     * check if we have to dump this entity or not
     *
     * @param  [type] $data [description]
     * @return boolean
     */
    protected function skipEntity($data)
    {

        if (is_array($this->dumpNamespaces)) {
            $ref = $data->getReflectionClass();
            if (!in_array($ref->getNamespaceName(), $this->dumpNamespaces)) {
                return true;
            }
        }

        if (is_array($this->whiteList)) {
            $ref = $data->getReflectionClass();
            $wl = $this->getNormalizedList($this->whiteList);
            $shortname = strtolower($ref->getShortName());
            if (!in_array($shortname, $wl)) {
                return true;
            }
        }

        if (is_array($this->blackList)) {
            $ref = $data->getReflectionClass();
            $wl = $this->getNormalizedList($this->blackList);
            $shortname = strtolower($ref->getShortName());
            if (in_array($shortname, $wl)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Generator\AbstractGenerator $generator
     * @param string                      $fixture
     * @param string                      $path
     * @param string                      $fileName
     */
    protected function writeFixture(AbstractGenerator $generator, $fixture, $path, $fileName)
    {
        $filesystem = new Filesystem();
        if (!$filesystem->exists($path)) {
            $filesystem->mkdir($path);
        }

        $fixture = $generator->prepareForWrite($fixture);

        file_put_contents($path .DIRECTORY_SEPARATOR. $fileName, $fixture);
    }

    protected function getAllMetadata()
    {
        return $this->objectManager->getMetadataFactory()->getAllMetadata();
    }


    protected function getNormalizedList($list)
    {
        foreach ($list as $key => $value) {
            $list[$key] = strtolower($value);
        }
        return $list;
    }

    abstract protected function getDumpOrder(array $classes);

}
