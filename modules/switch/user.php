<?php
/** @var eZModule $module */
$module = $Params['Module'];
$UserName = $Params['User'];

$currentUser = eZUser::currentUser();
$user = eZUser::fetchByName($UserName);
if ($user instanceof eZUser) {
    $accessArray = $user->accessArray();
    if (isset($accessArray['*']['*'])) {
        eZAudit::writeAudit('user-failed-login', array(
                'User id' => $currentUser->id(),
                'User login' => $currentUser->attribute('login'),
                'Switch to user login' => $UserName,
                'Comment' => 'Trying to switch to admin user')
        );
        $currentUser->logoutCurrent();
    } else {
        eZAudit::writeAudit('user-login', array(
            'User id' => $currentUser->id(),
            'User login' => $currentUser->attribute('login'),
            'Switch to user id' => $user->id(),
            'Switch to user login' => $UserName,
            'Comment' => 'Using switch/user administrative feature'
        ));
        eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));
    }
} else {
    eZAudit::writeAudit('user-failed-login', array(
            'User id' => $currentUser->id(),
            'User login' => $currentUser->attribute('login'),
            'Switch to user login' => $UserName,
            'Comment' => 'Trying to switch to a unknown user')
    );
}

return $module->redirectTo('/');
