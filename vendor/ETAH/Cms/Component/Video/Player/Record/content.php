<?php

namespace Etah\Cms\Component\Video\Player\Record;

use Etah\Mvc\Controller\BaseController as EtahMvcBaseController;
use Etah\Common\OperationSystem\Info as OperationSystemInfo;
use Etah\Common\File\Info as FileInfo;
use Zend\View\Model\ViewModel;
use Zend\Db\Sql\Where;


class Content extends EtahMvcBaseController{
	
	protected $contentReturnType  = 'viewModel';
	
	protected $controller = NULL;
	
	protected $customData = '';
	
	function __construct(){
		
		parent::__construct();
		
		$this->registDatabaseModel($this->serviceManager,'Base','Model','VideoModel');
		
		$this->registDatabaseModel($this->serviceManager,'Base','Model','VideoPlayinfoModel');
		
	}//function __construct() end
	
	/**
	 * 设置render方法返回的类型，是字符串型的html，还是对象性质的viewModel
	 * @param string $contentReturnType
	 * @return \Etah\Cms\Component\Video\Player\Live\Content
	 */
	public function setContentReturnType($contentReturnType){
		$this->contentReturnType = $contentReturnType;
		return $this;
	}
	
	public function setController($controller){
		$this->controller = $controller;
		return $this;
	}
	
	public function setVideoId($videoId){
		$this->videoId = $videoId;
		return $this;
	}
	
	public function setCustomData($data)
	{
		$this->customData = json_encode($data);
		return $this;
	}
	
	
	private function checkMemberVarible(){
	
		$array = array('contentReturnType','controller','videoId');
	
		foreach($array as $key=>$memberVarible){
				
			if(!isset($this->{$memberVarible}) || $this->{$memberVarible}==NULL ){
				die(  sprintf('使用点播播放器的时候没有对成员变量%s进行初始化',$memberVarible));
			}
		}
	
	}//function checkMemberVarible() end
	
	
	/**
	 * 组件作为控制器中的方法的代码片段的被调用的使用代码
	 */
	
	public function controllerRender(){
		
		$this->checkMemberVarible();
		
		$config = $this->serviceManager->get('config');
		
		$userAgentString = $this->controller->getRequest()->getHeader('useragent');
		$userAgentString = get_object_vars($userAgentString);
		$userAgentString = $userAgentString['value'];
		
		$operationSystemName = OperationSystemInfo::getOperationSystem($userAgentString);
		
		//------------------第二步：通过数据解析，得到视频相关信息--------------------------//
		
		$video = $this->videoModel->getRowById($this->videoId, array(
																			'id',
																			'name',
																			'pv',
																			'score',
																			'speaker',
																			'user_id',
																			'chapter_count',
																			'download_info'
																	));
		
		$phpRenderer = $this->serviceManager->get ( 'Zend\View\Renderer\PhpRenderer' );
		
		$viewModel = new ViewModel();
		
		$notice = '';
		
		if($operationSystemName=='unknown'){
			$notice.= '系统没有设别出您的操作系统类型，默认用flash播放器为您播放视频';
			$viewModel->setTemplate('Component/Video/Player/Record/Template/clearflash');
		}
		else if($operationSystemName=='windows'){
			$viewModel->setTemplate('Component/Video/Player/Record/Template/clearflash');
		}
		else if($operationSystemName=='ios'){
			
			//html5的时候，因为不会动态的获取播放参数，而且html5不支持多屏播放，所以只查询一个文件名信息
			$config = $this->serviceManager->get ( 'config' );
				
			$localServerIp = $config['website']['local_server_ip'];
				
			$where = new where();
			$where->equalTo('id',$this->videoId);
			$where->equalTo('chapter_number',0);
				
			$result = $this->videoPlayinfoModel->getRowById($this->videoId,array('filename'));
			//从视频播放表中查询出第一个可以播放的视频文件名
				
			$videoHttpProcessingDownloadPath = $this->getVideoHttpProcessingDownloadPath($this->controller,$localServerIp,$this->videoId,$result['filename']);
				
			if($video['chapter_count']>1){
				//只有当章节数大于1的时候才去形成HTML5标签的列表
				$videoHtml5UrlList = $this->getVideoChapterNumberHtml5UrlList($this->controller,$localServerIp,$this->videoId,$video['chapter_count']);
				$viewModel->setVariable('videoHtml5UrlList', $videoHtml5UrlList);
			}
				
		
			$viewModel->setVariable('videoFilePath', $videoHttpProcessingDownloadPath);
			$viewModel->setTemplate('Component/Video/Player/Record/Template/clearhtml5');
		}
		
		$viewModel->setVariable('video',$video);
		
		$viewModel->setVariable('customData',$this->customData);
		
		if(strtolower( $this->contentReturnType )=='html'){
				
			$html = $phpRenderer->render($viewModel);
				
			return $html;
				
		}
		else if( $this->contentReturnType =='viewModel'){
			return $viewModel;
		}
		
	}//function controllerRender() end
	
	
	
	
	/**
	 * 从实际的经过路由的控制器 和 从数据库中读取到的配置文件来决定视频播放器的内容
	 * 
	 * @param unknown_type $controller
	 * @param unknown_type $contentConfig
	 * @return unknown
	 */
	
	private function getDataByParseContentConfig($controller,$contentConfig){
		
		$id = $controller->params('id');
		//从路由中收到视频id，从而查询视频的相关信息
		
		$columns = array(   
							'id',
						    'name',
						    'pv',
							'score',
							'speaker',
							'user_id',
							'chapter_count',
							'download_info'
						);
		
		$video = $controller->videoModel->getRowById($id,$columns);
		
		return $video;
		
	}//function formatData() end
	
	
	/**
	 * 用来检验内容组件的内容参数是否符合要求
	 */
	
	private function checkContentConfigParameter($controller,$contentConfig){
		
		$modelList = array('videoModel','videoPlayinfoModel');
		
		foreach($modelList as $model){
			
			if(!isset($controller->{$model})){
				die(  sprintf('形成内容组件所需要的数据表%s在当前调用内容组件的控制器没有完成初始化',$model));
			}
			
		}
		
	}//function checkContentConfig() end
	
	
	public function render($controller,$contentConfig){
		
		$config = $this->serviceManager->get('config');
		
		$userAgentString = $controller->getRequest()->getHeader('useragent');
		$userAgentString = get_object_vars($userAgentString);
		$userAgentString = $userAgentString['value'];
		
		$operationSystemName = OperationSystemInfo::getOperationSystem($userAgentString);
		
		//通过数据解析，得到视频相关信息
		$video = $this->getDataByParseContentConfig($controller,$contentConfig);
		
		$phpRenderer = $this->serviceManager->get ( 'Zend\View\Renderer\PhpRenderer' );
		
		//通过解析配置文件，来选择使用html5标签,还是使用flash播放
		$contentConfig = unserialize($contentConfig);
		
		$viewModel = new ViewModel();
		
		$notice = '';
		
		if($operationSystemName=='unknown'){
			$notice.= '系统没有设别出您的操作系统类型，默认用flash播放器为您播放视频';
		}
		else{
			$operationSystemPlayerTypeKey = $operationSystemName.'_player_type';
			$operationSystemPlayerType = $contentConfig[$operationSystemPlayerTypeKey];
		}
		
		if(!in_array($operationSystemPlayerType,array('flash','html5'))){
			$notice.= '<br/>根据您的操作系统和后台播放器配置，既不是flash播放器也不是html5播放器,默认用flash播放器为您播放视频';
			$operationSystemPlayerType = 'flash';
		}
		
		if($operationSystemPlayerType=='flash'){
 			
 			$viewModel->setTemplate('Component/Video/Player/Record/Template/flash');
   		}
   		else  if($operationSystemPlayerType=='html5'){
		//html5的时候，因为不会动态的获取播放参数，而且html5不支持多屏播放，所以只查询一个文件名信息
		    
			$config = $this->serviceManager->get ( 'config' );
			
			$localServerIp = $config['website']['local_server_ip'];
			
			$id = $controller->params('id');
			//从路由中收到视频id，从而查询视频的相关信息
			
			$where = new where();
			$where->equalTo('id',$id);
			$where->equalTo('chapter_number',0);
			
			$result = $controller->videoPlayinfoModel->getRowById($id,array('filename'));
			//从视频播放表中查询出第一个可以播放的视频文件名
			
			$videoHttpProcessingDownloadPath = $this->getVideoHttpProcessingDownloadPath($controller,$localServerIp,$id,$result['filename']);
			
			if($video['chapter_count']>1){
			//只有当章节数大于1的时候才去形成HTML5标签的列表	
				$videoHtml5UrlList = $this->getVideoChapterNumberHtml5UrlList($controller,$localServerIp,$id,$video['chapter_count']);
				$viewModel->setVariable('videoHtml5UrlList', $videoHtml5UrlList);
			}
			
		
			$viewModel->setVariable('videoFilePath', $videoHttpProcessingDownloadPath);
			$viewModel->setTemplate('Component/Video/Player/Record/Template/html5');
		}
		
		
		$viewModel->setVariable('video',$video);
		
		$html = $phpRenderer->render($viewModel);
		
		return $html;
		
	}//function renderTemplate() end
	
	
	/**
	 * 根据网站配置读取
	 * @param  object $controller
	 * @param  string $localServerIp
	 * @param  int    $id
	 * @param  string $filename
	 * @return string $VideoHttpProcessingDownloadPath
	 */
	
	private function getVideoHttpProcessingDownloadPath($controller,$localServerIp,$id,$filename){
		
		$videoDiskPath = realpath( sprintf('%s/%s/%s',VIDEO_DISK_DIRECTORY,$id,$filename) );
		
		$videoHttpProcessingDownloadPath = sprintf('http://%s:8134/etah-mobile-vod/%s/%s.m3u8',$localServerIp,$id,$filename);
			
		return $videoHttpProcessingDownloadPath;
		
	}//function 
	
	
	
	
	/**
	 * 因为HTML5不支持一个页面中播放多个视频,所以只能取一节中的其中一个视频
	 * @param unknown_type $id
	 */
	
	private function getVideoChapterNumberHtml5UrlList($controller,$localServerIp,$id,$chapterCount){
		
		$result = array();
		
		for($i=1;$i<=$chapterCount;$i++){
			
			$where = new where();
			
			$where->equalTo('chapter_number',$i);
			
			$video = $controller->videoPlayinfoModel->getRowById($id,array('filename'),$where);
			
			if (sizeof($video) < 0 ){
				continue;
			}else{
				$videoHttpProcessingDownloadPath = $this->getVideoHttpProcessingDownloadPath($controller, $localServerIp,$id, $video['filename']);
				//得到第一个可以播放的视频的地址
				array_push($result,$videoHttpProcessingDownloadPath);
				break;
			}
			

		}
		
		return $result;
		
	}//function getVideoChapterNumberHtml5UrlList() end
	
	
	/**
	 * 得到视频点播的时候flashPlayer所需要的参数，只为flash播放器提供这些参数,html5播放器不需要这些内容
	 * 原来这个函数是从后台ajax动态获取参数的，现在改为在页面上形成的时候直接在页面上传值，减少对具体的控制器的依赖
	 */
	
	
	public function ajaxGetRecordVideoPlayInfoAction(){
	
		$request = $this->getRequest();
		 
		if(!$request->isXmlHttpRequest()){
			die('请不要尝试非法操作');
		}
	
		$postData = $request->getPost()->toArray();
	
		$id = $postData['video_id'];
		 
		if(!is_numeric($id)){
			$this->ajaxReturn(300,'视频ID错误，请不要尝试非法访问');
		}
		 
		//1.首先从视频信息表里面得到相关信息
		$videoColumns = array(
				'id',//视频的id
				'name',
				'index',
				'speaker',
				'chapter_count',//视频总共的章节数
				'edit_data',
				'download_info',
				'status',//视频的状态
				'download_status'//视频的下载状态
		);
		 
		$video = $this->videoModel->getRowById($id,$videoColumns);
		 
		if($video['status'] != "Y"){
			$this->ajaxReturn(300,'视频没有被审核，请不要尝试非法访问');
		}
		
		$websiteConfig = $this->serviceManager->get('config');
		$websiteConfig = $websiteConfig['website'];
		
		$svrIp = $websiteConfig['local_server_ip'];
		 
		//2.从视频播放表中里面的具体的播放参数
		
		$videoNum = 0;
		if(!isset($postData['chapter_number'])){
			$chapterNumber = $this->videoPlayinfoModel->GetMinimumChapterNumberById($id);
		}
		else{
			$chapterNumber = $postData['chapter_number'];
		}
		
		if (isset($postData['customData'])){
			
			if(sizeof($postData['customData']) < 1){
				$this->ajaxReturn(300,'视频还没有完成下载，请不要尝试非法访问');
			}
			
			$cunstonData = json_decode($postData['customData'],true);
			
			$videoNum = $cunstonData['num'];
			
			$svrIp = $cunstonData['svrIp'];
			
			$videoFileList = array();
			
			for ($i=0;$i<$videoNum;$i++){
				
				
				switch ($i){
					case 0:$RecordType = 'd';break;
					case 1:$RecordType = 'f';break;
					case 2:$RecordType = 'b';break;
				}
				
				$videoFile = 'H'.$cunstonData['folderName'].'_'.$RecordType.'_000.mp4';
				$videoFileList[$i] = array();
				$videoFileList[$i]['name'] = $cunstonData['folderName'].'/'.$videoFile;
				$videoFileList[$i]['time'] = $cunstonData['length'];
				
			}
			
			
		
		}else{
			
			if($video['download_status']!='finished'){
				$this->ajaxReturn(300,'视频还没有完成下载，请不要尝试非法访问');
			}
			
			$videoFileList  = $this->videoPlayinfoModel->GetVideoFileList($id,$chapterNumber);
				
			if($videoFileList===null){
				$this->ajaxReturn(300,'没有获得相应的视频的章节列表');
			}//if end
			
			foreach($videoFileList as $key=>$videoFile){
				$videoNum++;
				$videoFileList[$key] = array();
				$videoFileList[$key]['name'] = $videoFile['id'].'/'.$videoFile['filename'];
				$videoFileList[$key]['time'] = $videoFile['length'];
			
			}//foreach end
				
		}
		
	
		//3.从视频索引表拿到相关的视频索引信息，这部分的视频索引信息是从录播平台上传的
		$videoIndexInfo = $this->formatVideoIndex($id,$video['index']);
		 
		//4.临时设定设置，获取视频播放器的老师信息
		$speakerInfo    = $this->formatVideoSpeaker($video['speaker']);
		 
		//5.组合参数,满足播放器的格式要求
		
		$videoCoverHttpPath = sprintf("%s/media/video_cover/cover.jpg", dirname(  dirname( $request->getBasePath() )));
		
		
		//6.获取并处理剪辑数据
		
		
		$cutinfo = json_decode($video['edit_data'],true);
		
		$screen = array();
		for ($i=0;$i<$videoNum;$i++){
			array_push($screen, chr(ord('A')+$i));
		}
		$screen = implode(',',$screen);
		
		$RecordVideoPlayInfo = array(
				
				'name'=>$video['name'],
				'app'=>'teach_app',
				'screen'=>$screen,
				'chapterCount'=>$chapterNumber,
				'default'=>'',
				'ip'=> sprintf("%s:%s",$svrIp,$websiteConfig['record_video_play_parameter']['fms_port']),
				'video'=>$videoFileList,
		);

		$coverInfo = array(
				'start'=>array(
						'type'=>'pic',
						'link'=>$videoCoverHttpPath,
						'time'=>$websiteConfig['record_video_play_parameter']['cover_time']
						),
				);
		
		$cutinfo = json_decode($video['edit_data'],true);
		 
		$RecordVideoPlayParameterArray = array();
		$RecordVideoPlayParameterArray['playinfo']		 = $RecordVideoPlayInfo;
		$RecordVideoPlayParameterArray['coverinfo']		 = $coverInfo;
		$RecordVideoPlayParameterArray['cuttingInfo']	 = $cutinfo;
		$RecordVideoPlayParameterArray['debug']			 = $websiteConfig['record_video_play_parameter']['debug'];;
		 
		$this->ajaxReturn(200,'取得视频点播信息成功',$RecordVideoPlayParameterArray);
	
	}//function AjaxGetRecordVideoPlayInfo() end
	
	
	/**
	 * 格式化视频的索引信息，这些索引信息是在录播平台上生成的，通过recordHandle上传上来的
	 * 这些索引是显示在进度条上的，而不是显示在侧边栏上的
	 * @param unknown_type $videoId
	 * @param unknown_type $videoIndex
	 * @return multitype:|Ambigous <multitype:, string, unknown>
	 */
	
	private function formatVideoIndex($videoId,$videoIndex){
		 
		if($videoIndex==null||trim($videoIndex)==''){
			//因为有的视频根本不是录播平台上传的，也没有在资源平台加过索引，所以字段是空的，所以查询的时候可能是null值
			return array();
		}
	
		$videoIndexInfo = json_decode($videoIndex,true);
		 
		$videoIndexArray = $videoIndexInfo['filelist'];
		 
		if(sizeof($videoIndexArray)==0){
			return array();
		}
		 
		$basePath = dirname(  $this->getRequest()->getBasePath()  );
		 
		$formatVideoIndex = array();
		 
		foreach($videoIndexArray as $key=>$videoIndex){
			$formatVideoIndex[$key]['indexId'] = $key + 1;
			$formatVideoIndex[$key]['mediaId'] = 125;
			$formatVideoIndex[$key]['title']   = $videoIndex['title'];
			$formatVideoIndex[$key]['content'] = $videoIndex['content'];
	
			if(trim($videoIndex['filename'])!=''){
				$formatVideoIndex[$key]['filePath'] = sprintf("%s/media/video/%s/indexs",$basePath,$videoId,$videoIndex['filename']);
			}
	
		}
		 
		return $formatVideoIndex;
		 
	}//function _GetVideoIndex() end
	
	
	/**
	 * 格式化视频的教师信息，这些索引信息是在录播平台上生成的，通过recordHandle上传上来的
	 * 教师信息是显示在播放器的右边的
	 * @param unknown_type $videoId
	 * @param unknown_type $videoIndex
	 * @return multitype:|Ambigous <multitype:, string, unknown>
	 */
	private function formatVideoSpeaker($videoSpeaker){
	
		$speakerInfo = json_decode($videoSpeaker,true);
		 
		if(!is_array($speakerInfo)){
			//因为有的视频根本不是录播平台上传的，也没有在资源平台添加过教师的名称，所以字段是空的，所以查询的时候可能是null值
			return array();
		}
		 
		$speakerInfo['description'] 	= '吴老师的教学生涯的相关描述';
	
		$speakerInfo['sex']				= '男';
	
		$speakerInfo['teacherName'] 	= '吴老师';
	
		$speakerInfo['teacherPhoto'] 	= '/Teach/media/video_index/teacher/wu.jpg';
	
		$speakerInfo['teacherRemark'] 	= '资深物理讲师';
		 
		return $speakerInfo;
	}//function _GetVideoTeacher() end
	
	
	
	
	
}//class Common end