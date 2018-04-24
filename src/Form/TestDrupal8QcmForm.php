<?php

/**
 * @file
 * Contains \Drupal\test_d8\Form\TestDrupal8QcmForm
 */
namespace Drupal\test_d8\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\node\Entity\Node;
use GuzzleHttp\Cookie\SetCookie;
use Symfony\Component\BrowserKit\Request;

class TestDrupal8QcmForm extends FormBase {

    protected $numberQuestions;
    protected $timeLeft;
    protected $percent;

    public function __construct(){
        $testD8Settings = $this->config('test_d8.settings');
        $this->numberQuestions = $testD8Settings->get('number_of_questions');
        $this->timeLeft = $testD8Settings->get('time_to_complete_test');
        $this->percent = $testD8Settings->get('percent');
    }

    public function getFormId(){
        return 'testd8_form';
    }

    public function getTitle(NodeInterface $node = null) {
        return $this->t('Test @name', array(
            '@name' => $node->getTitle(),
        ));
    }

    public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = null){
        $nodeId = $node->id();

        $questionIds = $this->getAllQuestionsId($node);
        $questions = Paragraph::loadMultiple($questionIds);
        // get 40 random questions
        $questionsQcmList = $this->getCurrentQcmQuestions($questions);

        // nav mini-cercles
        $form['navisual'] = [
            '#type' => 'container',
            '#attributes' => [
                'class' => ['clearfix'],
                'id' => 'test_d8-navisual',
            ],
        ];
        $i = 0;
        foreach ($questionsQcmList as $data){
            $form['navisual']['circle'.$i] = [
                '#type' => 'html_tag',
                '#tag' => 'span',
                '#attributes' => [
                    'class' => ['test_d8-navisual-item'],
                    'data-qid' => $data['id'],
                    'data-pos' => $i,
                ],
                '#value' => ($i + 1),
            ];
            $i++;
        }

        // Q&A
        $i = 0;
        foreach ($questionsQcmList as $data){
            ++$i;

            $form['propositions'.$data['id']] = [
                '#type'     => 'radios',
                '#title'    => $this->t('Question @num', array('@num' => $i)),
                '#markup'   => '<div class="test_d8-question-text">'.$data['question'].'</div>',
                '#options'  => [
                    'p1' => $data['p1'],
                    'p2' => $data['p2'],
                    'p3' => $data['p3'],
                    'p4' => $data['p4'],
                ],
                '#prefix' => '<div class="test_d8-question'. ($i > 1 ? ' test_d8-hidden' : '') .'" id="test_d8-question'.$data['id'].'">',
                '#suffix' =>'</div>',
            ];

            $form['answer'.$data['id']] = [
                '#type' => 'hidden',
                '#value' => $data['reponse'],
            ];
        }

        // nav
        $form['previous'] = [
            '#type' => 'button',
            '#value' => '◀',
            '#title' => $this->t('Previous question'),
            '#attributes' => ['title' => $this->t('Previous question')],
            '#id' => 'test_d8-question-prev',
            '#prefix' => '<div id="test_d8-nav">',
        ];
        $form['current_question'] = [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#prefix' => '<span id="test_d8-question-curr">',
            '#suffix' => '</span>',
            '#value' => null,
        ];
        $form['next'] = [
            '#type' => 'button',
            '#value' => '▶',
            '#title' => $this->t('Next question'),
            '#attributes' => ['title' => $this->t('Next question')],
            '#id' => 'test_d8-question-next',
            '#suffix' => '</div>',
        ];
        $form['validation'] = [
            '#type' => 'submit',
            '#value' => t('Valider le test'),
            '#attributes' => ['disabled' => 'disabled'],
            '#id' => 'test_d8-submit',
        ];

        return $form;
    }

    protected function getAllQuestionsId($node){
        $field_questions = $node->get('field_questions')->getValue();
        $ids = [];
        foreach ($field_questions as $d){
            $ids[] = $d['target_id'];
        }
        return $ids;
    }

    // get 40 random questions
    protected function getCurrentQcmQuestions($questions){
        $tmpList = [];
        foreach ($questions as $id => $para){
            $tmpList[] = [
                'id' => $id,
                'question' => $this->paragraphGetValue($para, 'field_question'),
                'p1' => $this->paragraphGetValue($para, 'field_proposition_1'),
                'p2' => $this->paragraphGetValue($para, 'field_proposition_2'),
                'p3' => $this->paragraphGetValue($para, 'field_proposition_3'),
                'p4' => $this->paragraphGetValue($para, 'field_proposition_4'),
                'reponse' => $this->paragraphGetValue($para, 'field_reponse')
            ];
        }
        shuffle($tmpList);
        $questionsList = array_slice($tmpList, 0, $this->numberQuestions);

        return $questionsList;
    }

    protected function paragraphGetValue($object, $fieldname){
        return  $object->get($fieldname)->getValue()[0]['value'];
    }

    public function validateForm(array &$form, FormStateInterface $form_state){}

    public function submitForm(array &$form, FormStateInterface $form_state){
        $formData           = $form_state->getValues();
        $uid                = \Drupal::currentUser()->id();
        $node               = \Drupal::routeMatch()->getParameter('node');
        $nid                = $node->id();
        $certificationTitle = $node->getTitle();

        $scoreResult = $this->getScoreResult($formData);

        $this->setData([
            'uid' => $uid,
            'nid' => $nid,
            'certifTitle' => $certificationTitle,
            'scoreResult' => $scoreResult
        ]);

        $this->getScoreMessage($scoreResult, $certificationTitle);
        $form_state->setRedirect('entity.user.canonical', ['user' => $uid]);
    }

    // Score calculation
    protected function getScoreResult($formData){
        $score = 0;

        $answers = [];
        foreach ($formData as $field => $value){
            if ('answer' == substr($field, 0, 6)){
                $id = str_replace('answer', '', $field);
                $answers[$id] = $value;
            }
        }
        foreach ($formData as $field => $value){
            if ('propositions' == substr($field, 0, 12)){
                $id = substr($field, 12);
                $value = substr($value, 1);

                if ($answers[$id] == $value){
                    ++$score;
                }
            }
        }
        return $score;
    }

    // Node creation
    protected function setData($arg){
        $titleScore = 'Test Drupal 8 '.$arg['certifTitle'].' le '.format_date(\Drupal::time()->getCurrentTime(), 'format_date_coding_game');

        $node = Node::create(['type'=> 'score']);
        $node->set('title', $this->formatValueCT($titleScore));
        $node->set('uid', $this->formatValueCT($arg['uid'], 'target_id')) ;
        $node->set('field_score_nid', $this->formatValueCT($arg['nid'], 'target_id'));
        $node->set('field_score_result', $this->formatValueCT($arg['scoreResult']));
        $node->save();
    }

    // Formatting field value to create ContentType
    protected function formatValueCT($value, $key = 'value'){
        return array($key => $value);
    }

    // message flash
    protected function getScoreMessage($scoreResult, $certificationTitle){
        $messages = [
            'error' => $this->t('Test terminé.<br>Votre score est de <strong>@score/@nbQuestions</strong><br>'.
                'Continuez à vous entrainer !', [
                    '@score'       => $scoreResult,
                    '@nbQuestions' => $this->numberQuestions,
                ]
            ),
            'warning' => $this->t('Test terminé.<br>Votre score est de <strong>@score/@nbQuestions</strong><br>'.
                'Perséverez, vous y êtes presque !', [
                    '@score'       => $scoreResult,
                    '@nbQuestions' => $this->numberQuestions,
                ]
            ),
            'status' => $this->t('Test terminé.<br>Votre score est de <strong>@score/@nbQuestions</strong><br>'.
                'Félicitations ! En condition réelle, vous auriez obtenu votre certification @certifTitle', [
                    '@score'       => $scoreResult,
                    '@nbQuestions' => $this->numberQuestions,
                    '@certifTitle' => $certificationTitle,
                ]
            ),
        ];

        $levelAverage = $this->numberQuestions * $this->percent['average'] / 100;
        $levelGraduation = $this->numberQuestions * $this->percent['graduation'] / 100;

        if ($scoreResult < $levelAverage){
            $status = 'error';
        } elseif (($scoreResult >= $levelAverage) && ($scoreResult < $levelGraduation)){
            $status = 'warning';
        } elseif ($scoreResult >= $levelGraduation){
            $status = 'status';
        }

        \Drupal::messenger()->addMessage($messages[$status], $status, true);
    }
}
