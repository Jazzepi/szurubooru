<?php
namespace Szurubooru;

final class Dispatcher
{
	private $router;
	private $authService;

	public function __construct(
		\Szurubooru\Router $router,
		\Szurubooru\Helpers\HttpHelper $httpHelper,
		\Szurubooru\Services\AuthService $authService,
		\Szurubooru\ControllerRepository $controllerRepository)
	{
		$this->router = $router;
		$this->httpHelper = $httpHelper;

		//if script fails prematurely, mark it as fail from advance
		$this->httpHelper->setResponseCode(500);
		$this->authService = $authService;

		foreach ($controllerRepository->getControllers() as $controller)
			$controller->registerRoutes($router);
	}

	public function run()
	{
		global $start;
		try
		{
			$code = 200;
			$this->authorizeFromRequestHeader();
			$json = (array) $this->router->handle(
				$this->httpHelper->getRequestMethod(),
				$this->httpHelper->getRequestUri());
		}
		catch (\Exception $e)
		{
			$code = 400;
			$json = [
				'error' => $e->getMessage(),
				'trace' => $e->getTrace(),
			];
		}
		$end = microtime(true);
		$json['__time'] = $end - $start;

		$this->httpHelper->setResponseCode($code);
		$this->httpHelper->setHeader('Content-Type', 'application/json');
		$this->httpHelper->outputJSON($json);

		return $json;
	}

	private function authorizeFromRequestHeader()
	{
		$loginToken = $this->httpHelper->getRequestHeader('X-Authorization-Token');
		if ($loginToken)
			$this->authService->loginFromToken($loginToken);
	}
}
