<?php
namespace app\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\Query;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;

/**
 * User model
 *
 * @property integer $id
 * @property string $username
 * @property string $password_hash
 * @property string $password_reset_token
 * @property string $email
 * @property integer $show_email
 * @property string $auth_key
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 * @property string $full_name
 * @property string $sex
 * @property integer $country_id
 * @property string $city
 * @property string $about
 */
class User extends ActiveRecord implements IdentityInterface
{
    const STATUS_DELETED = 0;
    const STATUS_ACTIVE = 10;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%user}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    //ActiveRecord::EVENT_BEFORE_INSERT => 'created_at',
                    //ActiveRecord::EVENT_BEFORE_UPDATE => 'updated_at',
                ],
                'value' => function() {return date('U'); } // Unix timestamp
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['username', 'email', 'auth_key', 'status'], 'required'],
            [['status', 'country_id'], 'integer'],
            ['status', 'default', 'value' => self::STATUS_ACTIVE],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_DELETED]],
            [['created_at', 'updated_at'], 'safe'],
            [['username', 'password_hash', 'password_reset_token', 'email', 'auth_key', 'full_name', 'city'], 'string', 'max' => 255],
            [['about'], 'string'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'username' => 'Username',
            'password_hash' => 'Password Hash',
            'password_reset_token' => 'Password Reset Token',
            'email' => 'Email',
            'show_email' => 'Show Email to other users',
            'auth_key' => 'Auth Key',
            'status' => 'Status',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'full_name' => 'Full Name',
            'sex' => 'Sex',
            'country_id' => 'Country ID',
            'city' => 'City',
            'about' => 'About Yourself',
        ];
    }

    /*
    * Autoupdate created_at and updated_at fields before saving.
    */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if($insert)
                $this->created_at = time();
            $this->updated_at = time();
            return true;
        } else {
            return false;
        }
    }

    /**
     * Finds if current user is an admin(haves an admin rights)
     * @return bool
     */
    public function isAdmin()
    {
        $auth = Yii::$app->authManager;
        $getIsAdmin = $auth->getRolesByUser(Yii::$app->user->id);
        return (ArrayHelper::getValue($getIsAdmin, 'admin')) ? true : false;
    }

    /**
     * Gets a role of selected user
     * @param $id| ID of selected user
     * @return string Role of the user
     */
    public function getRole($id)
    {
        $auth = Yii::$app->authManager;
        $authorRole = $auth->getRole('author');
        $adminRole = $auth->getRole('admin');
        $getRoles = $auth->getRolesByUser($id);
        if(ArrayHelper::getValue($getRoles, 'author'))
            return 'Author';
        else if(ArrayHelper::getValue($getRoles, 'admin'))
            return 'Administrator';
    }

    /**
     * Sets a role to selected user
     * @param $id| ID of selected user
     * @param $role
     */
    public function setRole($id, $setRole)
    {
        $auth = Yii::$app->authManager;
        $authorRole = $auth->getRole('author');
        $adminRole = $auth->getRole('admin');
        $getRoles = $auth->getRolesByUser($id);
        if($setRole != null) {
            if($setRole == 'admin') {
                if(ArrayHelper::getValue($getRoles, 'author'))
                    $auth->revoke($authorRole, $id);
                if(ArrayHelper::getValue($getRoles, 'admin') == null)
                    $auth->assign($adminRole, $id);
            } elseif($setRole == 'author') {
                if(ArrayHelper::getValue($getRoles, 'admin'))
                    $auth->revoke($adminRole, $id);
                if(ArrayHelper::getValue($getRoles, 'author') == null)
                    $auth->assign($authorRole, $id);
            }
        }
    }
    /**
     * Do transaction which saves a model
     * @param $model
     * @return bool true if a transaction is successful
     */
    public function saveUser($model)
    {
        $transaction = Yii::$app->db->beginTransaction();
        if($model->save())
        {
            $transaction->commit();
            return true;
        } else {
            $transaction->rollBack();
            return false;
        }
    }

    /**
     * Do transaction which deletes a model
     * @param $model
     * @return bool true if a transaction is successful
     */
    public function deleteUser($model)
    {
        $transaction = Yii::$app->db->beginTransaction();
        if($model->delete())
        {
            $transaction->commit();
            return true;
        } else {
            $transaction->rollBack();
            return false;
        }
    }

    /**
     * Updates last login timestamp
     */
    private function setLastLoginTimestamp()
    {
        $this->last_login = time();
    }

    /**
     * Searches username by user ID
     * @param $array| All user that will be displayed
     * @param $id| ID of selected user
     * @return string| string Username of selected user
     */
    public function searchUsernameById($array, $id)
    {
        $i = 0;
        do
        if($array[$i]['id'] == $id)
            return $array[$i]['username'];
        while(++$i < count($array));
    }

    /**
     * Gets all the data of the user (user/create)
     */
    public function getUserData()
    {
        $user = new User;
        $user->username = $this->username;
        $user->email = $this->email;
        $user->full_name = $this->full_name;
        $user->country_id = $this->country_id;
        $user->city = $this->city;
        $user->about = $this->about;
        $user->last_login = $this->setLastLoginTimestamp();

        return $user;
    }

    /**
     * Finds all user where 'id' IN (string).
     * @param $author_IDs| String contains all user ID's
     * @return array|\yii\db\ActiveRecord[] Returns an array contains info about all user
     */
    public function findByAuthorIDs ($author_IDs)
    {
        if($author_IDs) {
            $query = User::find()
                ->select('id, username')
                ->where('id IN(' . $author_IDs . ')')
                ->asArray()
                ->all();
            return $query;
        } else {
            return false;
        }
    }

    /**
     * Finds identity by user id, his status must be active
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Find identity by access token is not must be implemented
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    /**
     * Finds user by username, his status must be active
     * @param string $username
     * @return static|null
     */
    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds user by password reset token
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken($token)
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'password_reset_token' => $token,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Finds out if password reset token is valid
     * @param string $token password reset token
     * @return boolean
     */
    public static function isPasswordResetTokenValid($token)
    {
        if (empty($token)) {
            return false;
        }

        $timestamp = (int) substr($token, strrpos($token, '_') + 1);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'];
        return $timestamp + $expire >= time();
    }

    /**
     * Sets $user->show_email property
     * @param string $mode True or false
     */
    public function setShowEmailProperty($mode = 'false')
    {
        $this->show_email = $mode;
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password by his hash
     *
     * @param string $password password to validate
     * @return boolean if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken()
    {
        $this->password_reset_token = null;
    }

    /**
     * Checks user privilegies for update/delete user or own user info
     * @return bool
     */
    public function checkUDPrivilegies($user)
    {
        return ((Yii::$app->user->can('updateOwnUser', ['user' => $user]) || Yii::$app->user->can('updateUser'))
            && (Yii::$app->user->can('deleteOwnUser', ['user' => $user]) || Yii::$app->user->can('deleteUser')));
    }
}
