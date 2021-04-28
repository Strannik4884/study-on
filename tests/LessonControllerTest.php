<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\Entity\Course;
use App\Entity\Lesson;

class LessonControllerTest extends AbstractTest
{
    private $coursesHomePath = '/courses';
    private $lessonsHomePath = '/lessons';

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
    public function getCoursesHomePath(): string
    {
        return $this->coursesHomePath;
    }

    /**
     * Get lessons home path
     */
    public function getLessonsHomePath(): string
    {
        return $this->lessonsHomePath;
    }

    /**
     * Check all HTTP statuses on lessons pages
     */
    public function testLessonPagesHTTPStatuses(): void
    {
        // init client
        $client = self::getClient();
        // init crawler
        $crawler = $client->request('GET', $this->getCoursesHomePath() . '/');
        $this->assertResponseOk();
        // get all courses links
        $coursesLinks = $crawler->filter('a.card-link')->links();
        // check every course
        foreach ($coursesLinks as $courseLink) {
            // go to course
            $crawler = $client->click($courseLink);
            $this->assertResponseOk();
            // get all course lessons links
            $lessonsLinks = $crawler->filter('a.card-link')->links();
            // check every lesson
            foreach ($lessonsLinks as $lessonLink) {
                $client->click($lessonLink);
                self::assertResponseIsSuccessful();
            }
        }
        // try get not exist lesson index page
        $client = self::getClient();
        $client->request('GET', $this->getLessonsHomePath() . '/42');
        $this->assertResponseNotFound();
    }

    /**
     * Check lesson create, read, delete
     */
    public function testLessonCreateValidateDelete(): void
    {
        // init client
        $client = self::getClient();
        // init crawler
        $crawler = $client->request('GET', $this->getCoursesHomePath() . '/');
        $this->assertResponseOk();
        // go to first course show page
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        // go to new lesson form
        $link = $crawler->filter('a.lesson-new-button')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        // get new lesson form
        $form = $crawler->selectButton('lesson-save-button')->form();
        // edit form
        $form['lesson[name]'] = 'New Lesson';
        $form['lesson[content]'] = 'Тестовый контент';
        $form['lesson[number]'] = '1';
        // get entity manager
        $entityManager = static::getEntityManager();
        $course = $entityManager->getRepository(Course::class)->findOneBy(['id' => $form['lesson[course]']->getValue()]);
        self::assertNotEmpty($course);
        // submit form
        $client->submit($form);
        // check redirect to course show page
        self::assertTrue($client->getResponse()->isRedirect($this->getCoursesHomePath() . '/' . $course->getId()));
        // follow redirect
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
        // go to new lesson show page
        $link = $crawler->filter('ul > li > a')->first()->link();
        $client->click($link);
        $this->assertResponseOk();
        // click remove lesson button
        $client->submitForm('lesson-remove-button');
        // check redirect to course show page
        self::assertTrue($client->getResponse()->isRedirect($this->getCoursesHomePath() . '/' . $course->getId()));
        // follow redirect
        $client->followRedirect();
        $this->assertResponseOk();

        // check new lesson form with incorrect name property
        // init client
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getCoursesHomePath() . '/');
        $this->assertResponseOk();
        // go to first course show page
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        // go to new lesson form
        $link = $crawler->filter('a.lesson-new-button')->link();
        $client->click($link);
        $this->assertResponseOk();
        // try to create lesson with name property which have more than 255 symbols
        $crawler = $client->submitForm('lesson-save-button', [
            'lesson[name]' => 'TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe
                TestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMeTestMe',
            'lesson[content]' => 'Тестовый контент',
            'lesson[number]' => '100',
        ]);
        // get form errors
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Длина названия не должна первышать 255 символов', $error->text());

        // check new lesson form with incorrect number property
        // init client
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getCoursesHomePath() . '/');
        $this->assertResponseOk();
        // go to first course show page
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        // go to new lesson form
        $link = $crawler->filter('a.lesson-new-button')->link();
        $client->click($link);
        $this->assertResponseOk();
        // try to create lesson with text in number property
        $crawler = $client->submitForm('lesson-save-button', [
            'lesson[name]' => 'New lesson',
            'lesson[content]' => 'Тестовый контент',
            'lesson[number]' => 'test',
        ]);
        // get form errors
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('This value is not valid.', $error->text());
        // try to create lesson with big integer value in number property
        $crawler = $client->submitForm('lesson-save-button', [
            'lesson[name]' => 'New lesson',
            'lesson[content]' => 'Тестовый контент',
            'lesson[number]' => '100000',
        ]);
        // get form errors
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Номер урока не может быть больше 10000', $error->text());
    }

    /**
     * Check course edit page
     */
    public function testLessonEditPage(): void
    {
        // init client
        $client = self::getClient();
        // init crawler
        $crawler = $client->request('GET', $this->getCoursesHomePath() . '/');
        $this->assertResponseOk();
        // get first course show page
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        // go to course lessons show page
        $link = $crawler->filter('ul > li > a')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        // go to lesson edit page
        $link = $crawler->filter('a.lesson-edit-button')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        // get form
        $form = $crawler->selectButton('lesson-save-button')->form();
        // get entity manager
        $entityManager = self::getEntityManager();
        // get lesson by number and course
        $lesson = $entityManager->getRepository(Lesson::class)->findOneBy([
            'number' => $form['lesson[number]']->getValue(),
            'course' => $form['lesson[course]']->getValue(),
        ]);
        // edit form
        $form['lesson[name]'] = 'Новый урок';
        $form['lesson[content]'] = 'Тестовый материал';
        // submit form
        $client->submit($form);
        // check redirect
        self::assertTrue($client->getResponse()->isRedirect($this->getCoursesHomePath() . '/' . $lesson->getCourse()->getId()));
        // follow redirect
        $client->followRedirect();
        $this->assertResponseOk();
    }
}
