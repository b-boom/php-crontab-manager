<?php
namespace tests\php\manager\crontab;

use tests\php\manager\crontab\mock\MockCrontabManager;

require_once __DIR__ . '/mock/MockCrontabManager.php';
require_once dirname(__DIR__) . '/src/CronEntry.php';

/**
 * Test class for CrontabManager.
 * Generated by PHPUnit on 2012-04-10 at 16:09:21.
 */
class CrontabManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \tests\php\manager\crontab\mock\MockCrontabManager
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $content = file_get_contents(__DIR__ . '/resources/cronfile.txt');
        
        $manager = new MockCrontabManager();
        $manager->setInitialContents($content);
        $manager->crontab = 'php ' . __DIR__ . '/mock/crontab.php';
        $manager->user = null;
        $this->object = $manager;
    }

    protected function tearDown()
    {
        unset($this->object);
    }

    public function testNewJob()
    {
        $job = $this->object->newJob();
        $this->assertInstanceOf('Crontab\CronEntry', $job);
        $actual = $job->render();
        $expected = '';
        $this->assertEquals($expected, $actual);

        $job = $this->object->newJob('* * 5 * * w # line comment');
        $actual = $job->render();
        $expected = '*	*	5	*	*	/usr/bin/w # line comment dm75rx';
        $this->assertEquals($expected, $actual);

        $job = $this->object->newJob('1 2 3 4 5 w');
        $actual = $job->render(false);
        $expected = '1	2	3	4	5	/usr/bin/w';
        $this->assertEquals($expected, $actual);
    }

    public function testAdd()
    {
        $job = $this->object->newJob('1 2 3 4 5 w');
        $this->object->add($job);

        $job2 = $this->object->newJob('2 3 4 5 6 w');
        $this->object->add($job2, '/tmp/a1');

        $this->object->save();

        $saved = $this->object->listJobs();
        $lines = explode("\n", $saved);

        $this->assertEquals(15, count($lines));

        $this->assertContains($job->render(true), $saved);
        $this->assertContains($job2->render(true), $saved);
        $needle = '*	*	*	*	*	w > /tmp/sysload';
        $this->assertContains($needle, $saved);
    }

    public function testReplace()
    {
        $job = $this->object->newJob('1 2 3 4 5 w');
        $this->object->add($job);
        $this->object->save();

        $before = $this->object->listJobs();

        $job2 = $this->object->newJob('2 3 4 5 6 uptime');

        $this->object->replace($job, $job2);
        $this->object->save();

        $after = $this->object->listJobs();
        $this->assertNotEquals($before, $after);
        $this->assertContains($job2->render(true), $after);
        $this->assertNotContains($job->render(true), $after);

        $this->assertContains($job->render(true), $before);
        $this->assertNotContains($job2->render(true), $before);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testTryEnableInvalid()
    {
        $tmpDir = sys_get_temp_dir();
        $tmpname = tempnam($tmpDir, 'phpunit');
        try {
            $enableContents = file_get_contents(__DIR__ . '/resources/invalid.txt');
            file_put_contents($tmpname, $enableContents);

            $this->object->enableOrUpdate($tmpname);
            $this->object->save();

        } catch (\Exception $exc) {
            unlink($tmpname);
            throw $exc;
        }
        unlink($tmpname);
    }

    public function testEnableOrUpdate()
    {
        $tmpDir = sys_get_temp_dir();
        $tmpname = tempnam($tmpDir, 'phpunit');
        try {
            $enableContents = file_get_contents(__DIR__ . '/resources/enable.txt');
            file_put_contents($tmpname, $enableContents);

            $before = $this->object->listJobs();
            $this->object->enableOrUpdate($tmpname);
            $this->object->save();
            $after = $this->object->listJobs();

            $this->assertNotEquals($before, $after);
            $needle = '1	2	3	4	5	/usr/bin/uptime';
            $this->assertContains($needle, $after);
            $this->assertNotContains($needle, $before);

            $initial = '*	*	*	*	*	w > /tmp/sysload';
            $this->assertContains($initial, $after);
            $this->assertContains($initial, $before);

            $this->object->disable($tmpname);
            $this->object->enableOrUpdate($tmpname);
            $this->object->save();
            $after2 = $this->object->listJobs();
            $this->assertEquals($after2, $after);
        } catch (\Exception $exc) {
            unlink($tmpname);
            throw $exc;
        }
        unlink($tmpname);
    }

    public function testDisable()
    {
        $tmpDir = sys_get_temp_dir();
        $tmpname = tempnam($tmpDir, 'phpunit');
        try {
            $enableContents = file_get_contents(__DIR__ .
                '/resources/enable.txt');
            file_put_contents($tmpname, $enableContents);

            $before = $this->object->listJobs();
            $before = preg_replace('/[\n]{3,}/m', "\n\n", $before);
            $this->object->enableOrUpdate($tmpname);
            $this->object->save();

            $this->object->disable($tmpname);
            $this->object->save();

            $after = $this->object->listJobs();

            $this->assertEquals($before, $after);
            $needle = '1	2	3	4	5	/usr/bin/uptime';
            $this->assertNotContains($needle, $after);

            $initial = '*	*	*	*	*	w > /tmp/sysload';
            $this->assertContains($initial, $after);
        } catch (\Exception $exc) {
            unlink($tmpname);
            throw $exc;
        }
        unlink($tmpname);
    }

    public function testSave()
    {
        $before = $this->object->listJobs();

        $job = $this->object->newJob('1 2 3 4 5 w');
        $this->object->add($job);
        $job2 = $this->object->newJob('2 3 4 5 6 w');
        $this->object->add($job2, '/tmp/a1');

        $this->object->save();

        $saved = $this->object->listJobs();

        $this->assertNotEquals($before, $saved);
        $this->assertNotEmpty($before);
        $this->assertNotEmpty($saved);

        $job3 = $this->object->newJob('3 4 5 6 7 w');
        $this->object->add($job3);

        $this->object->save();

        $savedSecond = $this->object->listJobs();
        $this->assertNotEmpty($savedSecond);
        $this->assertNotEquals($savedSecond, $saved);
        $this->assertNotEquals($savedSecond, $before);
        $this->assertContains($job3->render(true), $savedSecond);
        $this->assertNotContains($job3->render(true), $saved);
        $this->assertNotContains($job3->render(true), $before);

        $this->assertContains($job2->render(true), $savedSecond);
        $this->assertContains($job2->render(true), $saved);
        $this->assertNotContains($job2->render(true), $before);

        $job4 = $this->object->newJob('4 5 6 7 8 w');
        $this->object->cleanManager();
        $this->object->add($job4);
        $this->object->save(false);

        $savedThird = $this->object->listJobs();
        $this->assertContains($job4->render(true), $savedThird);
        $this->assertNotContains($job3->render(true), $savedThird);
        $this->assertNotContains($job2->render(true), $savedThird);
        $this->assertNotContains($job->render(true), $savedThird);

    }

    public function testListJobs()
    {
        $before = $this->object->listJobs();

        $job = $this->object->newJob('1 2 3 4 5 w');
        $this->object->add($job);
        $job2 = $this->object->newJob('2 3 4 5 6 w');
        $this->object->add($job2, '/tmp/a1');

        $this->object->save();

        $saved = $this->object->listJobs();

        $this->assertNotEquals($before, $saved);
        $this->assertNotEmpty($before);
        $this->assertNotEmpty($saved);

        $initial = '*	*	*	*	*	w > /tmp/sysload';
        $this->assertContains($initial, $saved);
        $this->assertContains($initial, $before);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEnableOrUpdateInvalid()
    {
        $this->object->enableOrUpdate(__DIR__ . '/not-existent-file.oo');
    }


    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDisableInvalid()
    {
        $this->object->disable(__DIR__ . '/not-existent-file.oo');
    }

    public function test_command()
    {
        $this->object->user = null;
        $this->object->crontab = '/usr/bin/crontab';

        $expected = '/usr/bin/crontab';
        $actual = $this->object->__mock_command();

        $this->assertEquals($expected, $actual);

        $this->object->user = 'some-user';
        $expected = 'sudo -u some-user /usr/bin/crontab';
        $actual = $this->object->__mock_command();

        $this->assertEquals($expected, $actual);
    }
}
