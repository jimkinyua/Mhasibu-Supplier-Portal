<?php
namespace app\controllers;
use app\models\SupplierPartnerDetails;
use app\models\VendorCard;
use Yii;
use yii\filters\AccessControl;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\Controller;
use frontend\models\Leave;
use yii\web\Response;
use app\models\MemberApplicationCard;


class DirectorsController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout', 'signup','index'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                     [
                        'actions' => ['logout','index'],
                        'allow' => true,
                        //'roles' => ['@'],
                        'matchCallback' => function($rule,$action){
                            return (Yii::$app->session->has('HRUSER') || !Yii::$app->user->isGuest);
                        },
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
            'contentNegotiator' =>[
                'class' => ContentNegotiator::class,
                'only' => ['getsignatories'],
                'formatParam' => '_format',
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                    //'application/xml' => Response::FORMAT_XML,
                ],
            ]
        ];
    }


    public function actionIndex(){
        $model = new SupplierPartnerDetails();
        $VendorCardModel = new VendorCard();

        $service = Yii::$app->params['ServiceName']['SupplierPartnerDetails'];

        if(Yii::$app->recruitment->HasProfileOnDynamics(Yii::$app->user->identity->id)){
            $service1 = Yii::$app->params['ServiceName']['VendorCard'];
            $filter = [
                'PortalId' => Yii::$app->user->identity->id,
            ];
            $result = Yii::$app->navhelper->getData($service1, $filter);     
            $model = Yii::$app->navhelper->loadmodel($result[0],$VendorCardModel);  
            return $this->render('index', [
                'model'=>$model,
                'Applicant'=>$this->ApplicantDetails($model->Key),
            ]);
        }


        $ApplicantData = $this->ApplicantDetails($Key);
        $model->Member_Category = $ApplicantData->Member_Category;
        return $this->render('index', ['model' => $model,
         'Applicant'=>$ApplicantData,
    ]);

    }

    public function getCountries(){
        $service = Yii::$app->params['ServiceName']['Countries'];
        $res = [];
        $Countries = \Yii::$app->navhelper->getData($service);
        foreach($Countries as $Country){
            if(!empty($Country->Code))
            $res[] = [
                'Code' => $Country->Code,
                'Name' => $Country->Name
            ];
        }

        return $res;
    }
    

 
    public function ApplicantDetails($key){
        $model = new VendorCard();
        $service = Yii::$app->params['ServiceName']['VendorCard'];
        $memberApplication = Yii::$app->navhelper->readByKey($service, $key);
       return $model = Yii::$app->navhelper->loadmodel($memberApplication,$model);
    }


    public function ApplicantDetailWithDocNum($Docnum){
        $model = new VendorCard();
        $service = Yii::$app->params['ServiceName']['VendorCard'];
        $filter = [
            'No'=>$Docnum
        ];
        $memberApplication = Yii::$app->navhelper->getData($service,$filter);
       return $model = Yii::$app->navhelper->loadmodel($memberApplication[0],$model);
    }



    public function actionCreate($Key){

        $model = new SupplierPartnerDetails();
        $service = Yii::$app->params['ServiceName']['SupplierPartnerDetails'];
        $ApplicantionData = $this->ApplicantDetails($Key);
        $model->Vendor_No = Yii::$app->user->identity->vendorNo;
        $model->Partner_ID_No = time();
        //$model->Supplier_No = $ApplicantionData->No;

       // Make Initial Request
       $result = Yii::$app->navhelper->postData($service, $model);
       if(is_object($result))
       {
           Yii::$app->navhelper->loadmodel($result, $model);
       }else{
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
           return ['note' => '<div class="alert alert-danger">Error Creating Imprest Line: '.$result.'</div>'];

       }

        if(Yii::$app->request->isAjax){
            return $this->renderAjax('create', [
                'model' => $model,
                'Countries'=>$this->getCountries(),
            ]);
        }
    }

    public function getMembers(){
        $service = Yii::$app->params['ServiceName']['Members'];
        $res = [];
        $Members = \Yii::$app->navhelper->getData($service);
        foreach($Members as $Member){
            if(!empty($Member->No))
            $res[] = [
                'Code' => $Member->No,
                'Name' => $Member->Name
            ];
        }

        return $res;
    }

    public function actionUpdate(){
        $service = Yii::$app->params['ServiceName']['SupplierPartnerDetails'];
        $filter = [
            'Key' => urldecode(Yii::$app->request->get('Key')),
        ];
        $result = Yii::$app->navhelper->readByKey($service, urldecode(Yii::$app->request->get('Key')));
        // echo '<pre>';
        // print_r($result);
        // exit;
        $ApplicationData = $this->ApplicantDetailWithDocNum($result->Vendor_No);


        $model = new SupplierPartnerDetails();
        //load nav result to model
        $model = $this->loadtomodel($result,$model);

        if(Yii::$app->request->post() && $this->loadpost(Yii::$app->request->post()['SupplierPartnerDetails'],$model)){
            $result = Yii::$app->navhelper->updateData($service,$model);
            if(!empty($result)){
                Yii::$app->session->setFlash('success','Kin Updated Successfully',true);
                return $this->redirect(['index', 'Key'=>$ApplicationData->Key]);
            }else{
                Yii::$app->session->setFlash('error','Error Updating Kin : '.$result,true);
                return $this->redirect(['index', 'Key'=>$ApplicationData->Key]);
            }

        }

        if(Yii::$app->request->isAjax){
            return $this->renderAjax('update', [
                'model' => $model,
                'Countries'=>$this->getCountries(),
            ]);
        }

 
    }

    public function actionDelete(){
        $service = Yii::$app->params['ServiceName']['SupplierPartnerDetails'];
        $result = Yii::$app->navhelper->deleteData($service,urldecode(Yii::$app->request->get('Key')));
        if(!is_string($result)){
            Yii::$app->session->setFlash('success','Signatory Removed Successfully .',true);
            return $this->redirect(['index']);
        }else{
            Yii::$app->session->setFlash('error','Signatory Removed Successfully: '.$result,true);
            return $this->redirect(['index']);
        }
    }


    public function actionView($ApplicationNo){
        $service = Yii::$app->params['ServiceName']['leaveApplicationCard'];
        $leaveTypes = $this->getLeaveTypes();
        $employees = $this->getEmployees();

        $filter = [
            'Application_No' => $ApplicationNo
        ];

        $leave = Yii::$app->navhelper->getData($service, $filter);

        //load nav result to model
        $leaveModel = new AccountSignatoriesList();
        $model = $this->loadtomodel($leave[0],$leaveModel);


        return $this->render('view',[
            'model' => $model,
            'leaveTypes' => ArrayHelper::map($leaveTypes,'Code','Description'),
            'relievers' => ArrayHelper::map($employees,'No','Full_Name'),
        ]);
    }



    public function actionGetsignatories($AppNo){
        $service = Yii::$app->params['ServiceName']['SupplierPartnerDetails'];
        $filter = [
            'Vendor_No' => Yii::$app->user->identity->vendorNo,
        ];
        $signatories = Yii::$app->navhelper->getData($service,$filter);

        // echo '<pre>';
        // print_r($signatories);
        // exit;


        $result = [];
        $count = 0;
      
        if(!is_object($signatories)){
            foreach($signatories as $kin){

                if(empty($kin->Partner_Name) && empty($kin->Partner_ID_No)){ //Useless KIn this One
                    continue;
                }
                ++$count;
                $link = $updateLink =  '';
                $data = $this->ApplicantDetailWithDocNum($kin->Vendor_No);
                if($data->Status == 'New'){
                    $updateLink = Html::a('Edit',['update','Key'=> urlencode($kin->Key) ],['class'=>'update btn btn-info btn-md']);
                    $link = Html::a('Remove',['delete','Key'=> urlencode($kin->Key) ],['class'=>'btn btn-danger btn-md']);
                }else{
                    $updateLink = '';
                    $link = '';
                }

                $result['data'][] = [
                    'index' => $count,
                    'Partner_Name' => !empty($kin->Partner_Name)?$kin->Partner_Name:'',
                    'Partner_ID_No' => !empty($kin->Partner_ID_No)?$kin->Partner_ID_No:'',
                    'Partner_Occupation' => !empty($kin->Partner_Occupation)?$kin->Partner_Occupation:'',
                    'PIN' => !empty($kin->PIN)?$kin->PIN:'',
                    'Mobile_No__x002B_254' => !empty($kin->Mobile_No__x002B_254)?$kin->Mobile_No__x002B_254:'',
                    'Update_Action' => $updateLink,
                    'Remove' => $link
                ];
            }
        
        }
           
      

        return $result;
    }

    public function getReligion(){
        $service = Yii::$app->params['ServiceName']['Religion'];
        $filter = [
            'Type' => 'Religion'
        ];
        $religion = \Yii::$app->navhelper->getData($service, $filter);
        return $religion;
    }

     /** Updates a single field */
     public function actionSetfield($field){
        $service = 'SupplierPartnerDetails';
        $value = Yii::$app->request->post('fieldValue');
       
        $result = Yii::$app->navhelper->Commit($service,[$field => $value],Yii::$app->request->post('Key'));
        Yii::$app->response->format = \yii\web\response::FORMAT_JSON;
        return $result;
          
    }
}