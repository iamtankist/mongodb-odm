<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional;

require_once __DIR__ . '/../../../../../TestInit.php';

use Documents\User,
    Documents\Employee,
    Documents\Manager,
    Documents\Address,
    Documents\Project;

class FunctionalTest extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{
    public function testIncrement()
    {
        $user = new User();
        $user->setUsername('jon');
        $user->setCount(100);

        $this->dm->persist($user);
        $this->dm->flush();
        $this->dm->clear();

        $user = $this->dm->findOne('Documents\User', array('username' => 'jon'));

        $user->incrementCount(5);
        $this->dm->flush();
        $this->dm->clear();

        $user = $this->dm->findOne('Documents\User', array('username' => 'jon'));
        $this->assertEquals(105, $user->getCount());

        $user->setCount(50);

        $this->dm->flush();
        $this->dm->clear();
        $user = $this->dm->findOne('Documents\User', array('username' => 'jon'));
        $this->assertEquals(50, $user->getCount());
    }

    public function testTest()
    {
        $employee = new Employee();
        $employee->setName('Employee');
        $employee->setSalary(50000.00);
        $employee->setStarted(new \DateTime());

        $address = new Address();
        $address->setAddress('555 Doctrine Rd.');
        $address->setCity('Nashville');
        $address->setState('TN');
        $address->setZipcode('37209');
        $employee->setAddress($address);

        $project = new Project('New Project');
        $manager = new Manager();
        $manager->setName('Manager');
        $manager->setSalary(100000.00);
        $manager->setStarted(new \DateTime());
        $manager->addProject($project);

        $this->dm->persist($employee);
        $this->dm->persist($address);
        $this->dm->persist($project);
        $this->dm->persist($manager);
        $this->dm->flush();

        $newProject = new Project('Another Project');
        $manager->setSalary(200000.00);
        $manager->addNote('Gave user 100k a year raise');
        $manager->incrementChanges(2);
        $manager->addProject($newProject);

        $this->dm->persist($newProject);
        $this->dm->flush();
        $this->dm->clear();

        $results = $this->dm->find('Documents\Manager', array('name' => 'Manager'))
            ->hydrate(false)
            ->getResults();
        $result = current($results);

        $this->assertEquals(1, count($results));
        $this->assertEquals(200000.00, $result['salary']);
        $this->assertEquals(2, count($result['projects']));
        $this->assertEquals(1, count($result['notes']));
        $this->assertEquals('Gave user 100k a year raise', $result['notes'][0]);
    }

    public function testNotAnnotatedDocumentAndTransientFields()
    {
        $this->dm->getDocumentCollection('Doctrine\ODM\MongoDB\Tests\Functional\NotAnnotatedDocument')->drop();

        $test = new NotAnnotatedDocument();
        $test->field = 'test';
        $test->transientField = 'w00t';
        $this->dm->persist($test);
        $this->dm->flush($test);
        $this->dm->clear();

        $test = $this->dm->find('Doctrine\ODM\MongoDB\Tests\Functional\NotAnnotatedDocument')
            ->getSingleResult();
        $this->assertNotNull($test);

        $test->field = 'ok';
        $this->dm->flush();
        $this->dm->clear();

        $test = $this->dm->find('Doctrine\ODM\MongoDB\Tests\Functional\NotAnnotatedDocument')
            ->hydrate(false)
            ->getResults();
        $document = current($test);
        $this->assertEquals(1, count($test));
        $this->assertEquals('ok', $document['field']);
        $this->assertFalse(isset($document['transientField']));
    }

    public function testNullFieldValuesAllowed()
    {
        $this->dm->getDocumentCollection('Doctrine\ODM\MongoDB\Tests\Functional\NullFieldValues')->drop();

        $test = new NullFieldValues();
        $test->field = null;
        $this->dm->persist($test);
        $this->dm->flush();

        $test = $this->dm->find('Doctrine\ODM\MongoDB\Tests\Functional\NullFieldValues')
            ->hydrate(false)
            ->getResults();
        $document = current($test);
        $this->assertNotNull($test);
        $this->assertNull($document['field']);

        $document = $this->dm->find('Doctrine\ODM\MongoDB\Tests\Functional\NullFieldValues')
            ->getSingleResult();
        $document->field = 'test';
        $this->dm->flush();
        $this->dm->clear();

        $document = $this->dm->find('Doctrine\ODM\MongoDB\Tests\Functional\NullFieldValues')
            ->getSingleResult();
        $this->assertEquals('test', $document->field);
        $document->field = null;
        $this->dm->flush();
        $this->dm->clear();

        $test = $this->dm->find('Doctrine\ODM\MongoDB\Tests\Functional\NullFieldValues')
            ->hydrate(false)
            ->getSingleResult();
        $this->assertNull($test['field']);
    }

    public function testAlsoLoadOnProperty()
    {
        $collection = $this->dm->getDocumentCollection('Doctrine\ODM\MongoDB\Tests\Functional\AlsoLoad');
        $collection->drop();
        $collection->insert(array(
            'bar' => 'w00t'
        ));
        $collection->insert(array(
            'foo' => 'cool'
        ));
        $collection->insert(array(
            'zip' => 'test'
        ));
        $documents = $this->dm->find('Doctrine\ODM\MongoDB\Tests\Functional\AlsoLoad')
            ->getResults();
        foreach ($documents as $document) {
            $this->assertNotNull($document->foo);
        }
    }

    public function testAlsoLoadOnMethod()
    {
        $collection = $this->dm->getDocumentCollection('Doctrine\ODM\MongoDB\Tests\Functional\AlsoLoad');
        $collection->drop();
        $collection->insert(array(
            'name' => 'Jonathan Wage'
        ));
        $collection->insert(array(
            'fullName' => 'Jonathan Wage'
        ));
        $documents = $this->dm->find('Doctrine\ODM\MongoDB\Tests\Functional\AlsoLoad')
            ->getResults();
        foreach ($documents as $document) {
            $this->assertEquals('Jonathan', $document->firstName);
            $this->assertEquals('Wage', $document->lastName);
        }
    }

    public function testSimplerEmbedAndReference()
    {
        $class = $this->dm->getClassMetadata('Doctrine\ODM\MongoDB\Tests\Functional\SimpleEmbedAndReference');
        $this->assertEquals('many', $class->fieldMappings['embedMany']['type']);
        $this->assertEquals('one', $class->fieldMappings['embedOne']['type']);
        $this->assertEquals('many', $class->fieldMappings['referenceMany']['type']);
        $this->assertEquals('one', $class->fieldMappings['referenceOne']['type']);
    }
}

class SimpleEmbedAndReference
{
    /** @Embed(targetDocument="Reference") */
    public $embedMany = array();

    /** @Reference(targetDocument="Embedded") */
    public $referenceMany = array();

    /** @Embed(targetDocument="Reference") */
    public $embedOne;

    /** @Reference(targetDocument="Embedded") */
    public $referenceOne;
}

class AlsoLoad
{
    /** @AlsoLoad({"bar", "zip"}) */
    public $foo;

    public $firstName;
    public $lastName;

    /** @AlsoLoad({"name", "fullName"}) */
    public function populateFirstAndLastName($name)
    {
        $e = explode(' ', $name);
        $this->firstName = $e[0];
        $this->lastName = $e[1];
    }
}

class NullFieldValues
{
    /** @Field(nullable=true) */
    public $field;
}

class NotAnnotatedDocument
{
    public $field;

    /** @Transient */
    public $transientField;
}