<?php

class Swift_Transport_StreamBufferTest extends \PHPUnit\Framework\TestCase
{
    public function testSettingWriteTranslationsCreatesFilters()
    {
        $factory = $this->createFactory();
        $factory->expects($this->once())
            ->method('createFilter')
            ->with('a', 'b')
            ->will($this->returnCallback(array($this, 'createFilter')));

        $buffer = $this->createBuffer($factory);
        $buffer->setWriteTranslations(array('a' => 'b'));
    }

    private function createFactory()
    {
        return $this->getMockBuilder('Swift_ReplacementFilterFactory')->getMock();
    }

    private function createBuffer($replacementFactory)
    {
        return new Swift_Transport_StreamBuffer($replacementFactory);
    }

    public function testOverridingTranslationsOnlyAddsNeededFilters()
    {
        $factory = $this->createFactory();
        $factory->expects($this->exactly(2))
            ->method('createFilter')
            ->will($this->returnCallback(array($this, 'createFilter')));

        $buffer = $this->createBuffer($factory);
        $buffer->setWriteTranslations(array('a' => 'b'));
        $buffer->setWriteTranslations(array('x' => 'y', 'a' => 'b'));
    }

    public function createFilter()
    {
        return $this->getMockBuilder('Swift_StreamFilter')->getMock();
    }
}
