<?php
App::uses('AppController', 'Controller');
/**
 * Users Controller
 *
 * @property User $User
 * @property PaginatorComponent $Paginator
 * @property SessionComponent $Session
 * @property FlashComponent $Flash
 */
class UsersController extends AppController {

/**
 * Components
 *
 * @var array
 */
	public $components = array('Paginator', 'Session', 'Flash');

	public function isAuthorized($user) {
	    // All registered users can add posts
	    if ($this->action === 'profile') {
	        return true;
	    }

	    return parent::isAuthorized($user);
	}

	public function beforeFilter() {
	    parent::beforeFilter();
	    // Allow users to register and logout.
	    $this->Auth->allow('add', 'logout', 'FBCallback');
	}

/**
 * index method
 *
 * @return void
 */
	public function index() {
		$this->User->recursive = 0;
		$this->set('users', $this->Paginator->paginate());
	}

/**
 * view method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function view($id = null) {
		if (!$this->User->exists($id)) {
			throw new NotFoundException(__('Invalid user'));
		}
		$options = array('conditions' => array('User.' . $this->User->primaryKey => $id));
		$this->set('user', $this->User->find('first', $options));
	}

/**
 * add method
 *
 * @return void
 */
	public function add() {
		if ($this->request->is('post')) {
			$this->User->create();
			if ($this->User->save($this->request->data)) {
				$this->Flash->success(__('The user has been saved.'));
				return $this->redirect(array('action' => 'index'));
			} else {
				$this->Flash->error(__('The user could not be saved. Please, try again.'));
			}
		}
		$profiles = $this->User->Profile->find('list');
		$this->set(compact('profiles'));
	}

/**
 * edit method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function edit($id = null) {
		if (!$this->User->exists($id)) {
			throw new NotFoundException(__('Invalid user'));
		}
		if ($this->request->is(array('post', 'put'))) {
			if ($this->User->save($this->request->data)) {
				$this->Flash->success(__('The user has been saved.'));
				return $this->redirect(array('action' => 'index'));
			} else {
				$this->Flash->error(__('The user could not be saved. Please, try again.'));
			}
		} else {
			$options = array('conditions' => array('User.' . $this->User->primaryKey => $id));
			$this->request->data = $this->User->find('first', $options);
		}
		$profiles = $this->User->Profile->find('list');
		$this->set(compact('profiles'));
	}

/**
 * delete method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function delete($id = null) {
		$this->User->id = $id;
		if (!$this->User->exists()) {
			throw new NotFoundException(__('Invalid user'));
		}
		$this->request->allowMethod('post', 'delete');
		if ($this->User->delete()) {
			$this->Flash->success(__('The user has been deleted.'));
		} else {
			$this->Flash->error(__('The user could not be deleted. Please, try again.'));
		}
		return $this->redirect(array('action' => 'index'));
	}

	public function FBCallback() {

		require_once APP . 'Vendor' . DS . 'autoload.php';

		$fb = new \Facebook\Facebook([
		  'app_id' => '2087240411540467',
		  'app_secret' => '310adaa561c4a6d26df158c7fab1cb0c',
		  'default_graph_version' => 'v3.0'
		]);

		$helper = $fb->getRedirectLoginHelper();

		try {
		  $accessToken = $helper->getAccessToken();
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
		  // When Graph returns an error
		  echo 'Graph returned an error: ' . $e->getMessage();
		  exit;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
		  // When validation fails or other local issues
		  echo 'Facebook SDK returned an error: ' . $e->getMessage();
		  exit;
		}

		if (! isset($accessToken)) {
		  if ($helper->getError()) {
		    header('HTTP/1.0 401 Unauthorized');
		    echo "Error: " . $helper->getError() . "\n";
		    echo "Error Code: " . $helper->getErrorCode() . "\n";
		    echo "Error Reason: " . $helper->getErrorReason() . "\n";
		    echo "Error Description: " . $helper->getErrorDescription() . "\n";
		  } else {
		    header('HTTP/1.0 400 Bad Request');
		    echo 'Bad request';
		  }
		  exit();
		}

		// The OAuth 2.0 client handler helps us manage access tokens
		$oAuth2Client = $fb->getOAuth2Client();

		// Get the access token metadata from /debug_token
		$tokenMetadata = $oAuth2Client->debugToken($accessToken);

		// Validation (these will throw FacebookSDKException's when they fail)
		$tokenMetadata->validateAppId('2087240411540467'); // Replace {app-id} with your app id
		// If you know the user ID this access token belongs to, you can validate it here
		//$tokenMetadata->validateUserId('123');
		$tokenMetadata->validateExpiration();

		if (!$accessToken->isLongLived()) {
		  // Exchanges a short-lived access token for a long-lived one
		  try {
		    $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
		  } catch (Facebook\Exceptions\FacebookSDKException $e) {
		    echo "<p>Error getting long-lived access token: " . $e->getMessage() . "</p>\n\n";
		    exit;
		  }
		}

		$_SESSION['Auth']['User']['fb_access_token'] = (string) $accessToken;

		$this->Flash->success(__('Sesión iniciada en Facebook correctamente'));
		return $this->redirect(array('controller' => 'users', 'action' => 'profile'));
	}

	public function profile() {

		require_once APP . 'Vendor' . DS . 'autoload.php';

		$sesion_nativa = '';
		$sesion_fb = '';

		$fb = new \Facebook\Facebook([
		  'app_id' => '2087240411540467',
		  'app_secret' => '310adaa561c4a6d26df158c7fab1cb0c',
		  'default_graph_version' => 'v3.0'
		]);

		//FB login url
		$_SESSION['Auth']['User']['fb_login_url'] = $fb->getRedirectLoginHelper()->getLoginUrl('http://localhost/cas_posts/users/fbcallback', array('email'));

		//perfil FB usuario logeado
		if ($this->Auth->user('fb_access_token')) {

			$response = $fb->get('/me?fields=id,name,last_name,picture,email', $this->Auth->user('fb_access_token'));

			if ($response) {
				$sesion_fb = $response->getGraphUser();
			}
		}

		$sesion_nativa = $this->Auth->user();
		
		$this->set(compact('sesion_nativa', 'sesion_fb'));
		//return false;
	}

	public function login() {
	    if ($this->request->is('post')) {
	        if ($this->Auth->login()) {
	            return $this->redirect($this->Auth->redirectUrl());
	        }
	        $this->Session->setFlash(__('Invalid username or password, try again'));
	    }
	}

	public function logout() {
	    return $this->redirect($this->Auth->logout());
	}
}
