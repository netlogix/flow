<?php
declare(ENCODING = 'utf-8');
namespace F3::FLOW3::MVC::Controller;

/*                                                                        *
 * This script is part of the TYPO3 project - inspiring people to share!  *
 *                                                                        *
 * TYPO3 is free software; you can redistribute it and/or modify it under *
 * the terms of the GNU General Public License version 2 as published by  *
 * the Free Software Foundation.                                          *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        */

/**
 * @package FLOW3
 * @subpackage MVC
 * @version $Id:F3::FLOW3::MVC::Controller::RequestHandlingController.php 467 2008-02-06 19:34:56Z robert $
 */

/**
 * An abstract base class for Controllers which can handle requests
 *
 * @package FLOW3
 * @subpackage MVC
 * @version $Id:F3::FLOW3::MVC::Controller::RequestHandlingController.php 467 2008-02-06 19:34:56Z robert $
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License, version 2
 */
class RequestHandlingController extends F3::FLOW3::MVC::Controller::AbstractController {

	/**
	 * @var F3::FLOW3::MVC::Request The current request
	 */
	protected $request;

	/**
	 * @var F3::FLOW3::MVC::Response The response which will be returned by this action controller
	 */
	protected $response;

	/**
	 * @var F3::FLOW3::MVC::Controller::Arguments Arguments passed to the controller
	 */
	protected $arguments;

	/**
	 * @var F3::FLOW3::MVC::Property::Mapper A property mapper for mapping the arguments
	 */
	protected $propertyMapper;

	/**
	 * @var array An array of supported request types. By default only web requests are supported. Modify or replace this array if your specific controller supports certain (additional) request types.
	 */
	protected $supportedRequestTypes = array('F3::FLOW3::MVC::Web::Request');

	/**
	 * @var F3::FLOW3::Property::MappingResults Mapping results of the arguments mapping process
	*/
	protected $argumentMappingResults;

	/**
	 * Constructs the controller.
	 *
	 * @param F3::FLOW3::Component::FactoryInterface $componentFactory A reference to the Component Factory
	 * @param F3::FLOW3::Package::ManagerInterface $packageManager A reference to the Package Manager
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function __construct(F3::FLOW3::Component::FactoryInterface $componentFactory, F3::FLOW3::Package::ManagerInterface $packageManager) {
		$this->arguments = $componentFactory->getComponent('F3::FLOW3::MVC::Controller::Arguments');
		parent::__construct($componentFactory, $packageManager);
	}

	/**
	 * Injects a property mapper
	 *
	 * @param F3::FLOW3::Property::Mapper $propertyMapper
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function injectPropertyMapper(F3::FLOW3::Property::Mapper $propertyMapper) {
		$this->propertyMapper = $propertyMapper;
	}

	/**
	 * Checks if the current request type is supported by the controller.
	 *
	 * If your controller only supports certain request types, either
	 * replace / modify the supporteRequestTypes property or override this
	 * method.
	 *
	 * @param F3::FLOW3::MVC::Request $request The current request
	 * @return boolean TRUE if this request type is supported, otherwise FALSE
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function canProcessRequest($request) {
		foreach ($this->supportedRequestTypes as $supportedRequestType) {
			if ($request instanceof $supportedRequestType) return TRUE;
		}
		return FALSE;
	}

	/**
	 * Processes a general request. The result can be returned by altering the given response.
	 *
	 * @param F3::FLOW3::MVC::Request $request The request object
	 * @param F3::FLOW3::MVC::Response $response The response, modified by this handler
	 * @return void
	 * @throws F3::FLOW3::MVC::Exception::UnsupportedRequestType if the controller doesn't support the current request type
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function processRequest(F3::FLOW3::MVC::Request $request, F3::FLOW3::MVC::Response $response) {
		if (!$this->canProcessRequest($request)) throw new F3::FLOW3::MVC::Exception::UnsupportedRequestType(get_class($this) . ' does not support requests of type "' . get_class($request) . '". Supported types are: ' . implode(' ', $this->supportedRequestTypes) , 1187701131);

		$this->request = $request;
		$this->request->setDispatched(TRUE);
		$this->response = $response;

		$this->initializeArguments();
		$this->mapRequestArgumentsToLocalArguments();
	}

	/**
	 * Forwards the request to another controller.
	 *
	 * @return void
	 * @throws F3::FLOW3::MVC::Exception::StopAction
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function forward($actionName, $controllerName = NULL, $packageKey = NULL, F3::FLOW3::MVC::Controller::Arguments $arguments = NULL) {
		$this->request->setDispatched(FALSE);
		$this->request->setControllerActionName($actionName);
		if ($controllerName !== NULL) $this->request->setControllerName($controllerName);
		if ($packageKey !== NULL) $this->request->setControllerPackageKey($packageKey);
		if ($arguments !== NULL) $this->request->setArguments($arguments);
		throw new F3::FLOW3::MVC::Exception::StopAction();
	}

	/**
	 * Redirects the web request to another uri.
	 *
	 * NOTE: This method only supports web requests and will thrown an exception if used with other request types.
	 *
	 * @param mixed $uri Either a string representation of a URI or a F3::FLOW3::Property::DataType::URI object
	 * @param integer $delay (optional) The delay in seconds. Default is no delay.
	 * @param integer $statusCode (optional) The HTTP status code for the redirect. Default is "303 See Other"
	 * @throws F3::FLOW3::MVC::Exception::UnsupportedRequestType If the request is not a web request
	 * @throws F3::FLOW3::MVC::Exception::StopAction
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function redirect($uri, $delay = 0, $statusCode = 303) {
		if (!$this->request instanceof F3::FLOW3::MVC::Web::Request) throw new F3::FLOW3::MVC::Exception::UnsupportedRequestType('redirect() only supports web requests.', 1220539734);

		$escapedUri = htmlentities($uri, ENT_QUOTES, 'utf-8');
		$this->response->setContent('<html><head><meta http-equiv="refresh" content="' . intval($delay) . ';url=' . $escapedUri . '"/></head></html>');
		$this->response->setStatus($statusCode);
		$this->response->setHeader('Location', (string)$uri);
		throw new F3::FLOW3::MVC::Exception::StopAction();
	}

	/**
	 * Sends the specified HTTP status immediately.
	 *
	 * NOTE: This method only supports web requests and will thrown an exception if used with other request types.
	 *
	 * @param integer $statusCode The HTTP status code
	 * @param string $statusMessage A custom HTTP status message
	 * @param string $content Body content which further explains the status
	 * @throws F3::FLOW3::MVC::Exception::UnsupportedRequestType If the request is not a web request
	 * @throws F3::FLOW3::MVC::Exception::StopAction
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function throwStatus($statusCode, $statusMessage = NULL, $content = NULL) {
		if (!$this->request instanceof F3::FLOW3::MVC::Web::Request) throw new F3::FLOW3::MVC::Exception::UnsupportedRequestType('throwStatus() only supports web requests.', 1220539739);

		$this->response->setStatus($statusCode, $statusMessage);
		if ($content === NULL) $content = $this->response->getStatus();
		$this->response->setContent($content);
		throw new F3::FLOW3::MVC::Exception::StopAction();
	}

	/**
	 * Returns the arguments which are defined for this controller.
	 *
	 * Use this information if you want to know about what arguments are supported and / or
	 * required by this controller or if you'd like to know about further information about
	 * each argument.
	 *
	 * @return F3::FLOW3::MVC::Controller::Arguments Supported arguments of this controller
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getArguments() {
		return $this->arguments;
	}

	/**
	 * Initializes (registers / defines) arguments of this controller.
	 *
	 * Override this method to add arguments which can later be accessed
	 * by the action methods.
	 *
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function initializeArguments() {
	}

	/**
	 * Maps arguments delivered by the request object to the local controller arguments.
	 *
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function mapRequestArgumentsToLocalArguments() {
		$this->propertyMapper->setTarget($this->arguments);
		foreach ($this->arguments as $argument) {
			if ($argument->getFilter() != NULL) $this->propertyMapper->registerFilter($argument->getFilter(), $argument->getName());
			if ($argument->getPropertyEditor() != NULL) $this->propertyMapper->registerPropertyEditor($argument->getPropertyEditor(), $argument->getName(), $argument->getPropertyEditorInputFormat());
		}

		$argumentsValidator = $this->componentFactory->getComponent('F3::FLOW3::MVC::Controller::ArgumentsValidator', $this->arguments);
		$this->propertyMapper->registerValidator($argumentsValidator);
		$this->propertyMapper->setAllowedProperties(array_merge($this->arguments->getArgumentNames(), $this->arguments->getArgumentShortNames()));
		$this->propertyMapper->map(new ::ArrayObject($this->request->getArguments()));

		$this->argumentMappingResults = $this->propertyMapper->getMappingResults();

		foreach ($this->argumentMappingResults->getErrors() as $propertyName => $error) {
			if (isset($this->arguments[$propertyName])) {
				$this->arguments[$propertyName]->setValidity(FALSE);
				$this->arguments[$propertyName]->addError($error);
			}
		}

		foreach ($this->argumentMappingResults->getWarnings() as $propertyName => $warning) {
			if (isset($this->arguments[$propertyName])) $this->arguments[$propertyName]->addWarning($warning);
		}
	}
}

?>