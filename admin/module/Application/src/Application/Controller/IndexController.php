<?php

namespace Application\Controller;

use Zend\View\View;

use Application\Controller\BaseController;
use Zend\Authentication\AuthenticationService as AuthenticationService;
use Zend\View\Model\ViewModel;
use Zend\Session\Container as Session;

use Zend\Authentication\Adapter\DbTable as dbTableAuthAdapter;
use Zend\Authentication\Result as Result;

use Zend\Mvc\MvcEvent;
use Application\Factory\ServiceLocatorFactory;
use Application\Filter\UserFilter;
use Zend\Navigation\Navigation as Navigation;

class IndexController extends BaseController
{
	protected $loginUser;
	protected $menuModel;
	protected $userModel;
	protected $userReportModel;
	
	
	public function __construct(){
		
		$serviceManager = ServiceLocatorFactory::getInstance();
		
		$this->getDbModel($serviceManager,'Application','Model','MenuModel');
		
		$this->getDbModel($serviceManager,'System','Model','UserModel');
		
	}
	
	
	public function showUserLoginAction()
	{
	
		$auth = new AuthenticationService();
		$Identity = $auth->getIdentity();
		
		if($Identity){
			$url = $this->url()->fromRoute('application',array('action'=>'admin'));
			return $this->redirect()->toUrl($url);
			
		}
		
		$viewModel = new ViewModel();
		
		return $viewModel;
		
	}
	
	/**
	 * 用户退出
	 */
	public function logoutAction()
	{
		//清空session
		$auth = new AuthenticationService();
		$auth->clearIdentity();
	
		//跳转至登录
		$url = $this->url()->fromRoute('application',array('controller'=>'Index','action'=>'showUserLogin'));
		return $this->redirect()->toUrl($url);
	}
	
	/**
	 * 用户认证
	 * @return boolean
	 */
	public function checkUserLoginAction()
	{
	
		$request = $this->getRequest();
		//得到getRequest方法得到Request对象
	
		if ($request->isXmlHttpRequest()){
			//判断是不是Ajax过来的请求,这里需要使用isXmlHttpRequest方法而不是isPost方法
				
			$postData = $request->getPost();
				
			$postData   = get_object_vars($postData);
			
			//首先验证验证码
			
			$session = new Session();
			$captcha = $session->captcha;
			
			if (md5($postData['captcha']) != $captcha){
				
				$this->returnMessage(0,'验证码错误!');
			}
			
			$userFilter = new UserFilter();
			//新建过滤对象
				
			$userLoginInputFilter = $userFilter->getInputFilter();
			//得到过滤对象
				
			$userLoginInputFilter->setValidationGroup('username','password');
			//设置三个name属性值，在登录表单中只需要验证这个三个字段
				
			$userLoginInputFilter->setData($postData);
			//为过滤器设置数据
										
			if ($userLoginInputFilter->isValid ()) {
				$userData = $userLoginInputFilter->getValues ();
			} else {
				$dataError = $userLoginInputFilter->getMessages ();
				foreach ( $dataError as $key => $error ) {
					$this->returnMessage ( 0, array_pop ( $error ) );
				}
			}
			
			//取得db对象和表名
			$serviceManager = $this->getServiceLocator();
				
			$dbAdapter = $serviceManager->get('Zend\Db\Adapter\Adapter');
				
	
			//初始化认证适配器
			$authAdapter = new dbTableAuthAdapter($dbAdapter,'oa_user','username','password','MD5(?)');
	
			$authAdapter->setIdentity($userData['username'])->setCredential($userData['password']);
				
			//认证用户
			$auth = new AuthenticationService();
			$result = $auth->authenticate($authAdapter);
				
			if(!$result->isValid()){
				//认证不通过，返回错误信息
				switch($result->getCode()){
					case Result::FAILURE_IDENTITY_NOT_FOUND:
						$this->returnMessage(0,'用户名不存在!');
						break;
					case Result::FAILURE_CREDENTIAL_INVALID:
						$this->returnMessage(0,'密码错误!');
						break;
					default:
						$this->returnMessage(0,'登录失败!');
						break;
				}
			}
				
			//认证结果
			$user = $authAdapter->getResultRowObject();
			//写入Session
			$auth->getStorage()->write((object)array(
					'id' => $user->id,
					'username' =>$user->username,
					'realname' => $user->realname,
			));
			
			$this->returnMessage(1,'验证成功!');
	    }
	
		//return false;
	}
	
	//简单的验证码生成
	public function captchaAction()
	{
		$session = new Session();
		$word = '';
	
		for($i=0;$i<4;$i++){
			$word .= dechex(mt_rand(0,15));
		}
	
		$sessionValueOfCaptcha = md5(strtolower($word));
		$session->offsetSet('captcha',$sessionValueOfCaptcha);
	
		$img = imagecreate(75,25);
		imagefill($img,0,0,imagecolorallocate($img,10,52,92));
		imagestring($img,5,mt_rand(18,20),mt_rand(5,8),$word,imagecolorallocate($img, 255,255,255));
	
		header('Content-Type:image/png');
		imagepng($img);
		imagedestroy($img);
	
		return false;
	}
	
	/**
	 * 后台首页
	 * @return \Zend\View\Model\ViewModel
	 */
	public function adminAction()
	{

		$loginUser = $this->getLoginUser();
		$realname = $loginUser->realname;
		
		
		$viewModel = new ViewModel();
		$viewModel->setVariable('realname',$realname);
		return $viewModel;

    }
    
   /**
    * 生成侧边栏的方法
    * @return \Zend\View\Model\ViewModel
    */
    public function sidebarAction()
    {
    	$module = $this->params('m');
    	
    	$viewModel = new ViewModel();
    	$viewModel->setVariable('module', $module);
    	
     	return $viewModel;

    }//function sidebar() end
    
    
    
    
    public function showPasswordChangeAction()
    {
    	
    	$viewModel = new ViewModel();
    	
    	return $viewModel;
    	
    }
    
    
    public function checkPasswordChangeAction()
    {
    	$loginUser = $this->getLoginUser();
    	
    	$request = $this->getRequest();
    	if (!$request->isPost ()) {
    		$this->returnMessage('300', '数据传入错误，请勿非法操作');
    	}
    		
    	$post = $request->getPost();
    		
    	$useFilter = new UserFilter ();
    	$useinputFilter = $useFilter->getInputFilter ();
    	$useinputFilter->setValidationGroup(array('password','confirmPassword'));
    	$useinputFilter->setData ( $post );
    		
    	if (!$useinputFilter->isValid ()) {
    		
    		$dataError = $useinputFilter->getMessages ();
    		foreach ( $dataError as $key => $error ) {
    			$this->returnMessage ( '300', array_pop ( $error ) );
    		}
    	}
    	
    	$userData = $useinputFilter->getValues ();
    			
    	if ($userData['password'] != $userData['confirmPassword']){
    		$this->returnMessage('300', '两次输入的密码不相同');
    	}
    			
    	$userData['password'] = md5($userData['password']);
    	unset($userData['confirmPassword']);
    	//修改用户的密码
    	
    	// 事务操作
    	$dbConnection = $this->getServiceLocator ()->get ( 'Zend\Db\Adapter\Connection' );
    	$dbConnection->beginTransaction ();
    	
    	try{
    		
    		
    		$this->userModel->updateUser ( $userData , array ("id" => $loginUser->id) );
    		
    		
	    } 
	    catch ( \Exception $e ) {
	    	
	    	
    		$this->returnMessage ( 300, $e->getMessage () );
    		
    		$dbConnection->rollback ();
    	}
    
    	$dbConnection->commit ();
   		
    	$this->returnMessage ( 200, '恭喜您，修改密码成功' );
    	
    }
    
   
}
