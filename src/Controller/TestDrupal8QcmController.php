<?php

namespace Drupal\test_d8\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\node\NodeInterface;

class TestDrupal8QcmController extends ControllerBase {

  protected $configTestD8;
  protected $request;
  protected $formBuilder;
  protected $formBuilderNS;

	public function __construct(Request $request, ConfigFactory $configFactory, FormBuilderInterface $formBuilder){
    $this->request = $request->request;
    $this->configTestD8 = $configFactory->getEditable("test_d8.settings");
    $this->formBuilder = $formBuilder;
    $this->formBuilderNS = $this->configTestD8->get('namespace.qcm_form');
	}

	public static function create(ContainerInterface $container){
		return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('config.factory'),
      $container->get('form_builder')
		);
	}

  // récupération et affichage du formulaire
	public function content(NodeInterface $node = null){
    return ['form' => $this->formBuilder->getForm($this->formBuilderNS, $node)];
	}

}
