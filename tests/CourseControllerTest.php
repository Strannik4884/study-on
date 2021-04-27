<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\Entity\Course;

class CourseControllerTest extends AbstractTest
{
    private $coursesHomePath = '/courses';

    /**
     * Override get fixtures class method
     */
    protected function getFixtures(): array
    {
        return [CourseFixtures::class];
    }

    /**
     * Get courses home path
     */
    public function getPath(): string
    {
        return $this->coursesHomePath;
    }

    /**
     * Course url provider
     */
    public function courseUrlProvider(): \Generator
    {
        yield [$this->getPath() . '/'];
        yield [$this->getPath() . '/new'];
    }

    /**
     * Check all HTTP statuses on course pages
     *
     * @dataProvider courseUrlProvider
     * @param $url
     */
    public function testCoursePagesHTTPStatuses($url): void
    {
        // init client
        $client = self::getClient();
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        // get entity manager
        $entityManager = self::getEntityManager();
        // get all courses
        $courses = $entityManager->getRepository(Course::class)->findAll();
        self::assertNotEmpty($courses);
        // check all course pages with data
        foreach ($courses as $course) {
            // check course index page (GET)
            self::getClient()->request('GET', $this->getPath() . '/' . $course->getId());
            $this->assertResponseOk();
            // check course edit page (GET)
            self::getClient()->request('GET', $this->getPath() . '/' . $course->getId() . '/edit');
            $this->assertResponseOk();
            // check course edit page (POST)
            self::getClient()->request('POST', $this->getPath() . '/' . $course->getId() . '/edit');
            $this->assertResponseOk();
            // check create new course page (POST)
            self::getClient()->request('POST', $this->getPath() . '/new');
            $this->assertResponseOk();
        }
        // try get not exist course index page
        $client = self::getClient();
        $url = $this->getPath() . '/42';
        $client->request('GET', $url);
        $this->assertResponseNotFound();
    }

    /**
     * Check course index page using crawler
     */
    public function testCourseIndexPage(): void
    {
        // init client
        $client = self::getClient();
        // init crawler
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();
        // get entity manager
        $entityManager = self::getEntityManager();
        // get all courses
        $courses = $entityManager->getRepository(Course::class)->findAll();
        self::assertNotEmpty($courses);
        // get courses count in database
        $realCoursesCount = count($courses);
        // get courses count on index page by card class
        $coursesCountOnIndexPage = $crawler->filter('div.card')->count();
        // compare values
        self::assertEquals($realCoursesCount, $coursesCountOnIndexPage);
    }

    /**
     * Check course show page using crawler
     */
    public function testCourseShowPage(): void
    {
        // get entity manager
        $entityManager = self::getEntityManager();
        // get all courses
        $courses = $entityManager->getRepository(Course::class)->findAll();
        self::assertNotEmpty($courses);
        // check every course show page
        foreach ($courses as $course) {
            // get course show page
            $crawler = self::getClient()->request('GET', $this->getPath() . '/' . $course->getId());
            $this->assertResponseOk();
            // get lessons count for course in database
            $realLessonsCount = count($course->getLessons());
            // get lessons count from course show page
            $lessonsCount = $crawler->filter('ul > li')->count();
            // compare values
            static::assertEquals($realLessonsCount, $lessonsCount);
        }
    }

    /**
     * Check course create, read, delete
     */
    public function testCourseCreateValidateDelete(): void
    {
        // check course create and delete
        // init client
        $client = self::getClient();
        // init crawler
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();
        // go to new course form
        $link = $crawler->filter('a.course-new-button')->link();
        $client->click($link);
        $this->assertResponseOk();
        // set form data
        $client->submitForm('course-save-button', [
            'course[code]' => 'PHPUNIT',
            'course[name]' => 'Test course',
            'course[description]' => 'Тестовый курс',
        ]);
        // check redirect to course index page
        self::assertTrue($client->getResponse()->isRedirect($this->getPath() . '/'));
        // follow redirect
        $crawler = $client->followRedirect();
        // get courses count from index page
        $coursesCountOnPage = $crawler->filter('div.card')->count();
        // compare values
        self::assertEquals(6, $coursesCountOnPage);
        // go to new course show page
        $link = $crawler->filter('a.card-link')->last()->link();
        $client->click($link);
        $this->assertResponseOk();
        // click on course remove button
        $client->submitForm('course-remove-button');
        // check redirect to course index page
        self::assertTrue($client->getResponse()->isRedirect($this->getPath() . '/'));
        // follow redicrect
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
        // get courses count from index page
        $coursesCountOnPage = $crawler->filter('div.card')->count();
        self::assertEquals(5, $coursesCountOnPage);

        // check new course form with incorrect code property
        // init client
        $client = self::getClient();
        // init crawler
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();
        // go to new course form
        $link = $crawler->filter('a.course-new-button')->link();
        $client->click($link);
        $this->assertResponseOk();
        // try to create course with code property which have more than 255 symbols
        $crawler = $client->submitForm('course-save-button', [
            'course[code]' => 'TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe',
            'course[name]' => 'Test course',
            'course[description]' => 'Тестовый курс',
        ]);
        // get form errors
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Длина кода не должна превышать 255 символов', $error->text());
        // try to create course with existing code
        $crawler = $client->submitForm('course-save-button', [
            'course[code]' => 'symfony-course',
            'course[name]' => 'Test course',
            'course[description]' => 'Тестовый курс',
        ]);
        // get form errors
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Данный код уже используется', $error->text());

        // check new course form with incorrect name property
        // init client
        $client = self::getClient();
        // init crawler
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();
        // go to new course form
        $link = $crawler->filter('a.course-new-button')->link();
        $client->click($link);
        $this->assertResponseOk();
        // try to create course with name property which have more than 255 symbols
        $crawler = $client->submitForm('course-save-button', [
            'course[code]' => 'PHPUNIT',
            'course[name]' => 'TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe',
            'course[description]' => 'Тестовый курс',
        ]);
        // get form errors
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Длина названия не должна превышать 255 символов', $error->text());

        // check new course form with incorrect description property
        // init client
        $client = self::getClient();
        // init crawler
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();
        // go to new course form
        $link = $crawler->filter('a.course-new-button')->link();
        $client->click($link);
        $this->assertResponseOk();
        // try to create course with description property which have more than 1000 symbols
        $crawler = $client->submitForm('course-save-button', [
            'course[code]' => 'PHPUNIT',
            'course[name]' => 'Test course',
            'course[description]' => 'TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe'
        ]);
        // get form errors
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Длина описания не должна превышать 1000 символов', $error->text());
    }

    /**
     * Check course edit page
     */
    public function testCourseEditPage(): void
    {
        // init client
        $client = self::getClient();
        // init crawler
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();
        // go to first course on index page
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        // go to edit course page
        $link = $crawler->filter('a.course-edit-button')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        // get edit form
        $form = $crawler->selectButton('course-save-button')->form();
        // get entity manager
        $entityManager = self::getEntityManager();
        // get course by code
        $course = $entityManager->getRepository(Course::class)->findOneBy(['code' => $form['course[code]']->getValue()]);
        // edit form
        $form['course[code]'] = 'TEST';
        $form['course[name]'] = 'Test course';
        $form['course[description]'] = 'Тестовый курс';
        // submit form
        $client->submit($form);
        // check redirect to course show page
        self::assertTrue($client->getResponse()->isRedirect($this->getPath() . '/' . $course->getId()));
        // follow redirect
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
    }
}
