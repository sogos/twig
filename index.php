<?php


use Symfony\Component\Validator\Constraints as Assert;


require_once('Kernel.php');

try {

	//var_dump($container['request']);
	$data = array(
		'name' => 'Your name',
		'email' => 'Your email',
		);


	$form = $container['form.factory']->createBuilder('form', $data)
	->add('name', 'text', array(
		'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 2)))
		))
	->add('email', 'text', array(
		'constraints' => array(new Assert\NotBlank(), new Assert\Email(), new Assert\Length(array('min' => 2)))
		))
	->add('gender', 'choice', array(
		'choices' => array(1 => 'male', 2 => 'female'),
		'expanded' => true,
		))
	->getForm();


	 if ('POST' == $container['request']->getMethod()) {
        $form->bind($container['request']);

		$valid = null; 
        if ($form->isValid()) {
        	$valid = true;
        } else {
        	$valid = false;
        }
    }

	echo $container['twig']->render('index.html.twig', array(
		'form' => $form->createView(),
		'isvalid' => $valid
		));

} catch (\Exception $e) {
	$exception_type = get_class($e);
	echo $container['twig']->render('Errors/exception.html.twig', array('exception' => $e, 'exception_type' => $exception_type));
}