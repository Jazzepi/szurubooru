<?php
namespace Szurubooru\Services;

class UserService
{
	private $config;
	private $validator;
	private $userDao;
	private $userSearchService;
	private $passwordService;
	private $emailService;
	private $fileService;
	private $thumbnailService;
	private $timeService;
	private $tokenService;

	public function __construct(
		\Szurubooru\Config $config,
		\Szurubooru\Validator $validator,
		\Szurubooru\Dao\UserDao $userDao,
		\Szurubooru\Dao\Services\UserSearchService $userSearchService,
		\Szurubooru\Services\PasswordService $passwordService,
		\Szurubooru\Services\EmailService $emailService,
		\Szurubooru\Services\FileService $fileService,
		\Szurubooru\Services\ThumbnailService $thumbnailService,
		\Szurubooru\Services\TimeService $timeService,
		\Szurubooru\Services\TokenService $tokenService)
	{
		$this->config = $config;
		$this->validator = $validator;
		$this->userDao = $userDao;
		$this->userSearchService = $userSearchService;
		$this->passwordService = $passwordService;
		$this->emailService = $emailService;
		$this->fileService = $fileService;
		$this->thumbnailService = $thumbnailService;
		$this->timeService = $timeService;
		$this->tokenService = $tokenService;
	}

	public function getByNameOrEmail($userNameOrEmail, $allowUnconfirmed = false)
	{
		$user = $this->userDao->findByName($userNameOrEmail);
		if ($user)
			return $user;

		$user = $this->userDao->findByEmail($userNameOrEmail, $allowUnconfirmed);
		if ($user)
			return $user;

		throw new \InvalidArgumentException('User "' . $userNameOrEmail . '" was not found.');
	}

	public function getByName($userName)
	{
		$user = $this->userDao->findByName($userName);
		if (!$user)
			throw new \InvalidArgumentException('User with name "' . $userName . '" was not found.');
		return $user;
	}

	public function getById($userId)
	{
		$user = $this->userDao->findById($userId);
		if (!$user)
			throw new \InvalidArgumentException('User with id "' . $userId . '" was not found.');
		return $user;
	}

	public function getFiltered(\Szurubooru\FormData\SearchFormData $formData)
	{
		$this->validator->validate($formData);
		$searchFilter = new \Szurubooru\Dao\SearchFilter($this->config->users->usersPerPage, $formData);
		return $this->userSearchService->getFiltered($searchFilter);
	}

	public function createUser(\Szurubooru\FormData\RegistrationFormData $formData)
	{
		$formData->validate($this->validator);

		$user = new \Szurubooru\Entities\User();
		$user->setRegistrationTime($this->timeService->getCurrentTime());
		$user->setLastLoginTime(null);
		$user->setAccessRank($this->userDao->hasAnyUsers()
			? \Szurubooru\Entities\User::ACCESS_RANK_REGULAR_USER
			: \Szurubooru\Entities\User::ACCESS_RANK_ADMINISTRATOR);

		$this->updateUserName($user, $formData->userName);
		$this->updateUserPassword($user, $formData->password);
		$this->updateUserAvatarStyle($user, \Szurubooru\Entities\User::AVATAR_STYLE_GRAVATAR);
		$this->updateUserEmail($user, $formData->email);
		return $this->userDao->save($user);
	}

	public function updateUser(\Szurubooru\Entities\User $user, \Szurubooru\FormData\UserEditFormData $formData)
	{
		$this->validator->validate($formData);

		if ($formData->avatarStyle !== null)
			$this->updateUserAvatarStyle($user, $formData->avatarStyle);

		if ($formData->avatarContent !== null)
			$this->updateUserAvatarContent($user, $formData->avatarContent);

		if ($formData->userName !== null)
			$this->updateUserName($user, $formData->userName);

		if ($formData->password !== null)
			$this->updateUserPassword($user, $formData->password);

		if ($formData->email !== null)
			$this->updateUserEmail($user, $formData->email);

		if ($formData->accessRank !== null)
			$this->updateUserAccessRank($user, $formData->accessRank);

		if ($formData->browsingSettings !== null)
			$this->updateUserBrowsingSettings($user, $formData->browsingSettings);

		return $this->userDao->save($user);
	}

	public function updateUserAvatarStyle(\Szurubooru\Entities\User $user, $newAvatarStyle)
	{
		$user->setAvatarStyle($newAvatarStyle);
	}

	public function updateUserAvatarContent(\Szurubooru\Entities\User $user, $newAvatarContentInBase64)
	{
		$target = $this->getCustomAvatarSourcePath($user);
		$this->fileService->saveFromBase64($newAvatarContentInBase64, $target);
		$this->thumbnailService->deleteUsedThumbnails($target);
	}

	public function updateUserName(\Szurubooru\Entities\User $user, $newName)
	{
		$this->assertNoUserWithThisName($user, $newName);
		$user->setName($newName);
	}

	public function updateUserPassword(\Szurubooru\Entities\User $user, $newPassword)
	{
		$user->setPasswordHash($this->passwordService->getHash($newPassword));
	}

	public function updateUserEmail(\Szurubooru\Entities\User $user, $newEmail)
	{
		if ($user->getEmail() === $newEmail)
		{
			$user->setEmailUnconfirmed(null);
		}
		else
		{
			$this->assertNoUserWithThisEmail($user, $newEmail);
			$user->setEmailUnconfirmed($newEmail);
			$user = $this->sendActivationEmailIfNeeded($user);
		}
	}

	public function updateUserAccessRank(\Szurubooru\Entities\User $user, $newAccessRank)
	{
		$user->setAccessRank($newAccessRank);
	}

	public function updateUserBrowsingSettings(\Szurubooru\Entities\User $user, $newBrowsingSettings)
	{
		$user->setBrowsingSettings($newBrowsingSettings);
	}

	public function updateUserLastLoginTime(\Szurubooru\Entities\User $user)
	{
		$user->setLastLoginTime($this->timeService->getCurrentTime());
		$this->userDao->save($user);
	}

	public function deleteUser(\Szurubooru\Entities\User $user)
	{
		$this->userDao->deleteById($user->getId());

		$avatarSource = $this->getCustomAvatarSourcePath($user);
		$this->fileService->delete($avatarSource);
		$this->thumbnailService->deleteUsedThumbnails($avatarSource);
	}

	public function sendPasswordResetEmail(\Szurubooru\Entities\User $user)
	{
		$token = $this->tokenService->createAndSaveToken($user->getName(), \Szurubooru\Entities\Token::PURPOSE_PASSWORD_RESET);
		$this->emailService->sendPasswordResetEmail($user, $token);
	}

	public function finishPasswordReset(\Szurubooru\Entities\Token $token)
	{
		if ($token->getPurpose() !== \Szurubooru\Entities\Token::PURPOSE_PASSWORD_RESET)
			throw new \Exception('This token is not a password reset token.');

		$user = $this->getByName($token->getAdditionalData());
		$newPassword = $this->passwordService->getRandomPassword();
		$user->setPasswordHash($this->passwordService->getHash($newPassword));
		$this->userDao->save($user);
		$this->tokenService->invalidateByName($token->getName());
		return $newPassword;
	}

	public function sendActivationEmail(\Szurubooru\Entities\User $user)
	{
		$token = $this->tokenService->createAndSaveToken($user->getName(), \Szurubooru\Entities\Token::PURPOSE_ACTIVATE);
		$this->emailService->sendActivationEmail($user, $token);
	}

	public function finishActivation(\Szurubooru\Entities\Token $token)
	{
		if ($token->getPurpose() !== \Szurubooru\Entities\Token::PURPOSE_ACTIVATE)
			throw new \Exception('This token is not an activation token.');

		$user = $this->getByName($token->getAdditionalData());
		$user = $this->confirmUserEmail($user);
		$this->userDao->save($user);
		$this->tokenService->invalidateByName($token->getName());
	}

	public function getCustomAvatarSourcePath(\Szurubooru\Entities\User $user)
	{
		return 'avatars' . DIRECTORY_SEPARATOR . $user->getId();
	}

	public function getBlankAvatarSourcePath()
	{
		return 'avatars' . DIRECTORY_SEPARATOR . 'blank.png';
	}

	private function sendActivationEmailIfNeeded(\Szurubooru\Entities\User $user)
	{
		if ($user->getAccessRank() === \Szurubooru\Entities\User::ACCESS_RANK_ADMINISTRATOR or !$this->config->security->needEmailActivationToRegister)
		{
			$user = $this->confirmUserEmail($user);
		}
		else
		{
			$this->sendActivationEmail($user);
		}
		return $user;
	}

	private function confirmUserEmail(\Szurubooru\Entities\User $user)
	{
		//security issue:
		//1. two users set their unconfirmed mail to godzilla@empire.gov
		//2. activation mail is sent to both of them
		//3. first user confirms, ok
		//4. second user confirms, ok
		//5. two users share the same mail --> problem.
		//by checking here again for users with such mail, this problem is solved with first-come first-serve approach:
		//whoever confirms e-mail first, wins.
		$this->assertNoUserWithThisEmail($user, $user->getEmailUnconfirmed());

		if ($user->getEmailUnconfirmed())
		{
			$user->setEmail($user->getEmailUnconfirmed());
			$user->setEmailUnconfirmed(null);
		}
		return $user;
	}

	private function assertNoUserWithThisName(\Szurubooru\Entities\User $owner, $nameToCheck)
	{
		$userWithThisName = $this->userDao->findByName($nameToCheck);
		if ($userWithThisName and $userWithThisName->getId() !== $owner->getId())
			throw new \DomainException('User with this name already exists.');
	}

	private function assertNoUserWithThisEmail(\Szurubooru\Entities\User $owner, $emailToCheck)
	{
		$userWithThisEmail = $this->userDao->findByEmail($emailToCheck);
		if ($userWithThisEmail and $userWithThisEmail->getId() !== $owner->getId())
			throw new \DomainException('User with this e-mail already exists.');
	}
}