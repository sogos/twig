<?php


use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\DefaultCsrfProvider;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\SessionCsrfProvider;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension as FormValidatorExtension;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\Forms;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Bridge\Twig\Form\TwigRenderer;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Validator\Validator;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\Mapping\ClassMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;
use Dotclear\ConstraintValidatorFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Reponse;
use Symfony\Component\Translation\Translator;

$loader = require __DIR__.'/vendor/autoload.php';

require_once(__DIR__.'/vendor/pimple/pimple/lib/Pimple.php');



$container = new \Pimple();
$container['locale'] = 'fr';
$container['request'] = $container->share(function($container) {
	$request = Request::createFromGlobals();
	return $request;
});

$container['twig_filesystem_location'] = __DIR__.'/views';
$container['twig.form.templates'] = array('form_div_layout.html.twig');
$container['twig.loader.filesystem'] =  $container->share(function ($container) {
	return new \Twig_Loader_Filesystem($container['twig_filesystem_location']);
});

$container['twig.loader.array'] = $container->share(function ($container) {
	return new \Twig_Loader_Array($container['twig.templates']);
});

$container['twig.loader'] = $container->share(function ($container) {
	return new \Twig_Loader_Chain(array(
		$container['twig.loader.array'],
		$container['twig.loader.filesystem'],
		));
});

$container['charset'] = 'utf8';
$container['debug'] = true;
$container['translator'] = function($container) {
	return new Translator($container['locale']);
};


$container['twig'] = function ($container) {
	$container['twig.options'] = array();
	$container['twig.form.templates'] = array('form_div_layout.html.twig');
	$container['twig.path'] = array();
	$container['twig.templates'] = array();
	$container['twig.options'] = array_replace(
		array(
			'charset'          => $container['charset'],
			'debug'            => $container['debug'],
			'strict_variables' => $container['debug'],
			), $container['twig.options']
		);

	$twig = new \Twig_Environment($container['twig.loader'], $container['twig.options']);
	$twig->addGlobal('app', $container);
	//$twig->addExtension(new TwigCoreExtension());

	if ($container['debug']) {
		$twig->addExtension(new \Twig_Extension_Debug());
	}

	if (class_exists('Symfony\Bridge\Twig\Extension\RoutingExtension')) {
		if (isset($container['url_generator'])) {
			$twig->addExtension(new RoutingExtension($container['url_generator']));
		}

		if (isset($container['translator'])) {
			$twig->addExtension(new TranslationExtension($container['translator']));
		}

		if (isset($container['security'])) {
			$twig->addExtension(new SecurityExtension($container['security']));
		}

		if (isset($container['form.factory'])) {
			$container['twig.form.engine'] = $container->share(function ($container) {
				return new TwigRendererEngine($container['twig.form.templates']);
			});

			$container['twig.form.renderer'] = $container->share(function ($container) {
				return new TwigRenderer($container['twig.form.engine'], $container['form.csrf_provider']);
			});

			$twig->addExtension(new FormExtension($container['twig.form.renderer']));

                    // add loader for Symfony built-in form templates
			$reflected = new \ReflectionClass('Symfony\Bridge\Twig\Extension\FormExtension');
			$path = dirname($reflected->getFileName()).'/../Resources/views/Form';
			$container['twig.loader']->addLoader(new \Twig_Loader_Filesystem($path));
		}
	}

	return $twig;
};


if (!class_exists('Locale') && !class_exists('Symfony\Component\Locale\Stub\StubLocale')) {
	throw new \RuntimeException('You must either install the PHP intl extension or the Symfony Locale Component to use the Form extension.');
}
if (!class_exists('Locale')) {
	$r = new \ReflectionClass('Symfony\Component\Locale\Stub\StubLocale');
	$path = dirname(dirname($r->getFilename())).'/Resources/stubs';

	require_once $path.'/functions.php';
	require_once $path.'/Collator.php';
	require_once $path.'/IntlDateFormatter.php';
	require_once $path.'/Locale.php';
	require_once $path.'/NumberFormatter.php';
}
$container['form.secret'] = md5(__DIR__);
$container['form.type.extensions'] = $container->share(function ($container) {
	return array();
});
$container['form.type.guessers'] = $container->share(function ($container) {
	return array();
});

$container['form.extensions'] = $container->share(function ($container) {
	$extensions = array(
		new CsrfExtension($container['form.csrf_provider']),
		new HttpFoundationExtension(),
		);

	if (isset($container['validator'])) {
		$extensions[] = new FormValidatorExtension($container['validator']);

		if (isset($container['translator'])) {
			$r = new \ReflectionClass('Symfony\Component\Form\Form');
			$container['translator']->addResource('xliff', dirname($r->getFilename()).'/Resources/translations/validators.'.$container['locale'].'.xlf', $container['locale'], 'validators');
		}
	}

	return $extensions;
});

$container['form.factory'] = $container->share(function ($container) {
	return Forms::createFormFactoryBuilder()
	->addExtensions($container['form.extensions'])
	->addTypeExtensions($container['form.type.extensions'])
	->addTypeGuessers($container['form.type.guessers'])
	->getFormFactory()
	;
});

$container['form.csrf_provider'] = $container->share(function ($container) {
	if (isset($container['session'])) {
		return new SessionCsrfProvider($container['session'], $container['form.secret']);
	}

	return new DefaultCsrfProvider($container['form.secret']);
});

if (isset($container['form.factory'])) {
	$container['twig.form.engine'] = $container->share(function ($container) {
		return new TwigRendererEngine($container['twig.form.templates']);
	});

	$container['twig.form.renderer'] = $container->share(function ($container) {
		return new TwigRenderer($container['twig.form.engine'], $container['form.csrf_provider']);
	});

	$container['twig']->addExtension(new FormExtension($container['twig.form.renderer']));

                    // add loader for Symfony built-in form templates
	$reflected = new \ReflectionClass('Symfony\Bridge\Twig\Extension\FormExtension');
	$path = dirname($reflected->getFileName()).'/../Resources/views/Form';

	$container['twig.loader']->addLoader(new \Twig_Loader_Filesystem($path));
}


$container['validator'] = $container->share(function ($container) {
	$r = new \ReflectionClass('Symfony\Component\Validator\Validator');

	if (isset($container['translator'])) {
		$container['translator']->addResource('xliff', dirname($r->getFilename()).'/Resources/translations/validators.'.$container['locale'].'.xlf', $container['locale'], 'validators');
	}

	return new Validator(
		$container['validator.mapping.class_metadata_factory'],
		$container['validator.validator_factory'],
		isset($container['translator']) ? $container['translator'] : new DefaultTranslator()
		);
});

$container['validator.mapping.class_metadata_factory'] = $container->share(function ($container) {
	return new ClassMetadataFactory(new StaticMethodLoader());
});

$container['validator.validator_factory'] = $container->share(function() use ($container) {
	$validators = isset($container['validator.validator_service_ids']) ? $container['validator.validator_service_ids'] : array();

	return new ConstraintValidatorFactory($container, $validators);
});