<?php


class catalogApi
{

    public $db;
    public $requestUri = [];
    public $requestParams = [];

    protected $action = '';
    protected $login = 'admin'; //admin
    protected $password = '21232f297a57a5a743894a0e4a801fc3'; //admin
    protected $token = 'a57a5a743894';


    public function __construct($db)
    {
        header("Access-Control-Allow-Orgin: *");
        header("Access-Control-Allow-Methods: *");
        header("Content-Type: application/json");

        //Массив GET параметров разделенных слешем
        $path = explode('?', $_SERVER['REQUEST_URI']);
        $this->requestUri = explode('/', trim($path[0], '/'));
        $this->requestParams = $_REQUEST;
        $this->db = $db;
        $this->token = md5($this->token . date('Y-m-d'));

    }

    public function run()
    {

        $this->action = end($this->requestUri) . 'Action';

        if (array_shift($this->requestUri) !== 'api' || !method_exists($this, $this->action)) {
            throw new RuntimeException('API Not Found', 404);
        }


        //Если метод(действие) определен в дочернем классе API
        if (method_exists($this, $this->action)) {
            return $this->{$this->action}();
        } else {
            throw new RuntimeException('Invalid Method', 405);
        }
    }

    protected function response($data, $status = 500)
    {
        header("HTTP/1.1 " . $status . " " . $this->requestStatus($status));
        return json_encode($data);
    }

    private function requestStatus($code)
    {
        $status = [
            200 => 'OK',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
        ];
        return ($status[$code]) ? $status[$code] : $status[500];
    }


    /**
     * Метод POST
     * Вывод списка всех записей
     * http://site.com/api/categories
     * @return string
     */
    public function loginAction()
    {
        if ($this->login == $this->requestParams['login'] && $this->password == md5($this->requestParams['password'])) {
            return $this->response($this->token, 200);
        }

        return $this->response('Login failed', 404);
    }

    /**
     * Метод GET
     * Вывод списка всех записей
     * http://site.com/api/getCategories
     * @return string
     */
    public function getCategoriesAction()
    {
        $categories = $this->db->query("SELECT * FROM category");

        if ($categories) {
            return $this->response($categories, 200);
        }
        return $this->response('Data not found', 404);
    }

    /**
     * Метод POST
     * Просмотр товаров категории (по id)
     * http://site.com/api/products + параметры запроса category
     * @return string
     */
    public function getProductsAction()
    {
        $category = $this->requestParams['category'];
        if (!is_array($category)) {
            $category = [$category];
        }
        $products = $this->db->query("SELECT p.* FROM product p 
                                        LEFT JOIN product_to_category ptc ON p.id = ptc.product_id 
                                        LEFT JOIN category c ON ptc.category_id = c.id
                                        WHERE c.id IN (:categories)", ['categories' => $category]);
        if ($products) {
            return $this->response($products, 200);
        }
        return $this->response('Data not found', 404);
    }



    /**
     * Метод POST
     * Создание новой категории
     * http://site.com/api/createCategory + параметры запроса name
     * @return string
     */
    public function createCategoryAction()
    {
        if ($this->checkAccess()){
            return $this->response("Authentication failed please login again", 500);
        }
        $name = $this->requestParams['name'] ?? '';
        if ($name) {
            $result = $this->db->query("INSERT INTO category(name) VALUES(:name)", ["name" => 'test_create']);

            if ($result) {
                return $this->response(['id' => $this->db->lastInsertId()], 200);
            }
        }
        return $this->response("Saving error", 500);
    }

    /**
     * Метод POST
     * Создание нового товара
     * http://site.com/api/createProduct + параметры запроса name, category_id
     * @return string
     */
    public function createProductAction()
    {

        if ($this->checkAccess()){
            return $this->response("Authentication failed please login again", 500);
        }
        $name = $this->requestParams['name'] ?? '';
        $category_id = $this->requestParams['category_id'] ?? '';
        if ($name && $category_id) {
            $category = $this->db->single("SELECT id FROM category WHERE id=? ", [$category_id]);
            if ($category) {

                $result = $this->db->query("INSERT INTO product(name) VALUES(:name)", ["name" => $name]);

                if ($result) {
                    $product_id = $this->db->lastInsertId();
                    $result = $this->db->query("INSERT INTO product_to_category(product_id, category_id) VALUES(:product, :category)",
                       ["product" => $product_id, 'category' => $category]);
                    if ($result) {
                        return $this->response(['id' => $product_id], 200);
                    }
                }
            }

        }
        return $this->response("Saving error", 500);
    }

    /**
     * Метод POST
     * Обновление категории (по ее id)
     * http://site.com/api/updateCategory + параметры запроса массив полеей
     * @return string
     */
    public function updateCategoryAction()
    {
        if ($this->checkAccess()){
            return $this->response("Authentication failed please login again", 500);
        }
        $fields = [
            'id' => $this->requestParams['id'],
            'name' => $this->requestParams['name']
        ];
        $category = $this->db->single("SELECT id FROM category WHERE id=? ", [$fields['id']]);

        if ($category) {

            $updateFields = [];
            foreach ($fields as $name => $value) {
                if ($name != 'id') {
                    $updateFields[] = $name . ' = :' . $name;
                }
            }


            $result = $this->db->query("UPDATE category SET " . join(',', $updateFields) . " WHERE id = :id", $fields);

            if ($result) {
                return $this->response('Data updated.', 200);
            }
        }else{
            return $this->response("Category not found", 400);
        }
        return $this->response("Update error", 400);
    }

    /**
     * Метод POST
     * Обновление категории (по ее id)
     * http://site.com/api/updateProduct + параметры запроса массив полей
     * @return string
     */
    public function updateProductAction()
    {
        if ($this->checkAccess()){
            return $this->response("Authentication failed please login again", 500);
        }
        $fields = [
            'id' => $this->requestParams['id'],
            'name' => $this->requestParams['name']
        ];
        $product = $this->db->single("SELECT id FROM product WHERE id=? ", [$fields['id']]);

        if ($product) {

            $updateFields = [];
            foreach ($fields as $name => $value) {
                if ($name != 'id') {
                    $updateFields[] = $name . ' = :' . $name;
                }
            }

            $result = $this->db->query("UPDATE product SET " . join(',', $updateFields) . " WHERE id = :id", $fields);

            if ($result) {
                return $this->response('Data updated.', 200);
            }
        }else{
            return $this->response("Category not found", 400);
        }
        return $this->response("Update error", 400);
    }

     /**
      * Метод POST
      * Удаление отдельной записи (по ее id)
      * http://site.com/api/deleteCategory
      * @return string
      */
     public function deleteCategoryAction()
     {
         if ($this->checkAccess()){
             return $this->response("Authentication failed please login again", 500);
         }
         $category = $this->db->single("SELECT id FROM category WHERE id=? ", [$this->requestParams['id']]);

         if($category){
             $result = $this->db->query("DELETE FROM category WHERE id = :id", ["id"=>$category]);

             if ($result) {
                 return $this->response('Data updated.', 200);
             }
         }else{
             return $this->response("Category with id=".$this->requestParams['id']." not found", 404);
         }



         return $this->response("Delete error", 500);
     }

    /**
     * Метод POST
     * Удаление отдельной записи (по ее id)
     * http://site.com/api/deleteProduct
     * @return string
     */
    public function deleteProductAction()
    {
        if ($this->checkAccess()){
            return $this->response("Authentication failed please login again", 500);
        }
        $product = $this->db->single("SELECT id FROM category WHERE id=? ", [$this->requestParams['id']]);

        if($product){
            $result = $this->db->query("DELETE FROM category WHERE id = :id", ["id"=>$product]);

            if ($result) {
                return $this->response('Data updated.', 200);
            }
        }else{
            return $this->response("Product with id=".$this->requestParams['id']." not found", 404);
        }

        
        return $this->response("Delete error", 500);
    }

    protected function checkAccess(){
        if ($this->token == $this->requestParams['token']){
            return true;
        }
        return false;
    }
}