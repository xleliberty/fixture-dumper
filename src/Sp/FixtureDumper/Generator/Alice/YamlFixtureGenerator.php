<?php

namespace Sp\FixtureDumper\Generator\Alice;

use Symfony\Component\Yaml\Yaml;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Sp\FixtureDumper\Converter\Alice\YamlVisitor;
use Sp\FixtureDumper\Generator\AliceGenerator;

/**
 * @author Martin Parsiegla <martin.parsiegla@gmail.com>
 */
class YamlFixtureGenerator extends AliceGenerator
{

    /**
     * {@inheritdoc}
     */
    public function createFilename(ClassMetadata $metadata, $multipleFiles = true)
    {
        if ($multipleFiles) {
            return lcfirst($this->namingStrategy->fixtureName($metadata) .'.yml');
        }

        return 'fixtures.yml';
    }

    protected function prepareData(ClassMetadata $metadata, array $data)
    {
        $yaml = new Yaml();

        return $yaml->dump(array($metadata->getName() => $data), 3);
    }

    protected function getDefaultVisitor()
    {
        return new YamlVisitor();
    }
}