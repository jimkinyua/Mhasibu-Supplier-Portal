<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\RegisterForm;
use app\models\ResetPasswordForm;
use yii\base\InvalidArgumentException;
use yii\web\BadRequestHttpException;
use app\models\VerifyEmailForm;
use app\models\VendorCard;


class CompanyProfileController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout', 'index', ],
                'rules' => [
                    [
                        'actions' => ['logout', 'index',],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    public function beforeAction($action){
        if(Yii::$app->user->isGuest){
            $this->layout = 'guest';
            $this->goHome();
        }
        if (!parent::beforeAction($action)) {
            return false;
        }
        return true; // or false to not run the action
    }


    public function actionIndex(){
        $model = new VendorCard();
        $service = Yii::$app->params['ServiceName']['VendorCard'];
        if(Yii::$app->request->post() && $this->loadpost(Yii::$app->request->post()['VendorCard'],$model)){
            // exit('f');

            $filter = [
                'PortalId' => Yii::$app->user->identity->id,
            ]; 
            $refresh = Yii::$app->navhelper->getData($service,$filter);
            $model->Key = $refresh[0]->Key;
            $result = Yii::$app->navhelper->updateData($service,$model);
            if(is_object($result)){
                Yii::$app->session->setFlash('success','Supplier Data Added Successfully',true);
                return $this->redirect(['index']);
            }else{
                Yii::$app->session->setFlash('error',$result);
                return $this->redirect(['index']);
            }
        }//End Saving Profile Gen data

        if(Yii::$app->recruitment->HasProfileOnDynamics(Yii::$app->user->identity->id)){
            $filter = [
                'PortalId' => Yii::$app->user->identity->id,
            ];
            $result = Yii::$app->navhelper->getData($service, $filter);
            // echo '<pre>';
            // print_r($result);
            // exit;
      
            $model = $this->loadtomodel($result[0],$model);  

            return $this->render('index', [
                'model'=>$model,
                'Applicant'=>$this->ApplicantDetails($model->Key),
                'SupplierCategories'=>$this->getSupplierCategoryies(),
            ]);
        }

        

        //No Profile Detected. Let's Create Them One
        $model->E_Mail = Yii::$app->user->identity->email;
        $model->PortalId = Yii::$app->user->identity->id;
        $model->Phone_No = Yii::$app->user->identity->companyPhoneNo;
        $result = Yii::$app->navhelper->postData($service,$model);
        if(is_object($result)){ //Added Sucesfully
            //Load to Model
            $model = $this->loadtomodel($result,$model);  
            return $this->render('view', ['model'=>$model]);
        }
        Yii::$app->session->setFlash('error', $result);
        return $this->goHome();


    }

    /*Created Supplier Profle*/
   public function actionCreate(){

    $model = new VendorCard();
    $model->PortalId = Yii::$app->user->identity->id;
    $model->No = Yii::$app->user->identity->vendorNo;

    /*print '<pre>';
    print_r($model); exit;*/

    
    $service = Yii::$app->params['ServiceName']['VendorCard'];

     return $this->render('create',[
         'model' => $model,
         'towns' => [],
         'countries' => [],
         'scategories' => [],
         'locations' =>  [],
         'ShipmentMethods' => [],
         'paymentTerms' => [],// $this->dropdown('PaymentTerms','Code','Description'),
         'paymentMethods' => [], // $this->dropdown('PaymentMethoq1                                                                                                                                                    `aAAaszds','Code','Description'),
         'VendorBankAccounts' => [], // $this->dropdown('VendorBankAccountList','Code','Name'),
        
     ]);
 }

    public function actionDeclaration($Key){
        $model = new VendorCard();
        $service = Yii::$app->params['ServiceName']['VendorCard'];
        $memberApplication = Yii::$app->navhelper->readByKey($service, urldecode($Key));

        $model = $this->loadtomodel($memberApplication,$model);

        if($model->load(Yii::$app->request->post()) && Yii::$app->request->post()){
         
            $service = Yii::$app->params['ServiceName']['PortalFactory'];

            $data = [
                'applicationNo' =>  $memberApplication->Application_No,
                'sendMail' => 1,
                'approvalUrl' => '',
            ];
       
            $result = Yii::$app->navhelper->PortalWorkFlows($service,$data,'Submitmemberprofile');
            //       echo '<pre>';
            // print_r($result);
            // exit;
            

            if(!is_string($result)){
                Yii::$app->session->setFlash('success', 'Profile Submitted Succesfully', true);
                return $this->redirect(['site/index']);
            }else{
    
                Yii::$app->session->setFlash('error', $result);
                return $this->redirect(['index', 'Key'=>$memberApplication->Key]);
    
            }
        }

        return $this->render('declaration', [
            'model' => $model,
        ]);
    }

    public function getSupplierCategoryies(){
        $service = Yii::$app->params['ServiceName']['SupplierCategory'];
        $res = [];
        $SupplierCategories = \Yii::$app->navhelper->getData($service);
        foreach($SupplierCategories as $SupplierCategory){
            if(!empty($SupplierCategory->Category_Code))
            $res[] = [
                'Code' => $SupplierCategory->Category_Code,
                'Name' => $SupplierCategory->Description
            ];
        }

        return $res;
    }

    public function ApplicantDetails($key){
        $model = new VendorCard();
        $service = Yii::$app->params['ServiceName']['VendorCard'];
        $memberApplication = Yii::$app->navhelper->readByKey($service, $key);
       return $model = $this->loadtomodel($memberApplication,$model);
    }


    // Utility Functions



    public function dropdown($service,$from,$to){
        $service = Yii::$app->params['ServiceName'][$service];
        $result = \Yii::$app->navhelper->getData($service, []);
        return Yii::$app->navhelper->refactorArray($result,$from,$to);
    }






}
