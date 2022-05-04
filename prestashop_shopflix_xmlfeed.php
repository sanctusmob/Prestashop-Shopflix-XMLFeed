<?php

//For Debug

/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/

$time_start = microtime(true); 

include(dirname(__FILE__).'/config/config.inc.php');
$_SERVER['REQUEST_METHOD'] = "POST";
include(_PS_ROOT_DIR_.'/init.php');

$configuration = Configuration::getMultiple(array(
    'PS_LANG_DEFAULT',
    'PS_SHIPPING_FREE_PRICE',
    'PS_SHIPPING_HANDLING',
    'PS_SHIPPING_METHOD',
    'PS_SHIPPING_FREE_WEIGHT',
    'PS_CARRIER_DEFAULT'));

//Set greek language id
$id_lang = 2;
$image_thump_size = 'large_default';
$output_filename = 'shopflix_dev';

$link = new Link();

/**
 * Return available categories.
*
* @param bool|int $idLang Language ID
* @param bool $active Only return active categories
* @param bool $order Order the results
* @param string $sqlFilter Additional SQL clause(s) to filter results
* @param string $orderBy Change the default order by
* @param string $limit Set the limit
*                      Both the offset and limit can be given
*
* @return array Categories
*
* public static function getCategories($idLang = false, $active = true, $order = true, $sqlFilter = '', $orderBy = '', $limit = '')
*
*/
//Get All Gategories
$categories = Category::getCategories($id_lang, false, false);
$allCategories = array();
foreach ($categories as $category) 
{
    $allCategories[$category['id_category']] = $category;
}
  
/**
 * Get all available products.
*
* @param int $id_lang Language id
* @param int $start Start number
* @param int $limit Number of products to return
* @param string $order_by Field for ordering
* @param string $order_way Way for ordering (ASC or DESC)
*
* @return array Products details
*
* public static function getProducts($id_lang, $start, $limit, $order_by, $order_way, $id_category = false, $only_active = false, Context $context = null)
*
*/
//Limit products. Set low value for debug. Set zero for unlimit.
$limit = 0;
//Set skroutz categorie products or 2 for home.
$feed_product_categorie = 2;

//Set only active products
$only_active_products = true;

//For shipping calculation if needed. 
$id_zone = 9; //Europe (Greece)
$id_warehouse = 0;

$xml_feed = new SimpleXMLElement('<products></products>');

$psproducts = Product::getProducts($id_lang, 0, $limit, 'id_product', 'ASC', $feed_product_categorie, true);
//Add more products from other categories
//$psproducts = array_merge($psproducts, Product::getProducts($id_lang, 0, $limit, 'id_product', 'ASC', 1153, true));

foreach ($psproducts as $psproduct)
{
    //For Debug
    //var_dump($psproduct);

    $id_product = $psproduct['id_product'];    
    //Get product quantity
    $quantity = Product::getQuantity($id_product);

    //Add to feed only products with stock and available for order
    if($quantity > 0 && $psproduct['available_for_order'] == 1) // && $psproduct['price'] > 1.60
    {
        $clsProduct = new Product($id_product, true, $id_lang, '1');
        $sfproduct = new ShopflixProduct();

        $mpn = $psproduct['reference'];

        $sfproduct->sku = $psproduct['ean13'];
        $sfproduct->mpn = $mpn;
        $sfproduct->ean = $psproduct['ean13'];
        $sfproduct->name = $psproduct['manufacturer_name'] . ' ' . $psproduct['name'] . ' ' . $mpn;	
        $sfproduct->quantity = $quantity;

        /**
         * Create a link to a product.
         *
         * @param Product|array|int $product Product object (can be an ID product, but deprecated)
         * @param string|null $alias
         * @param string|null $category
         * @param string|null $ean13
         * @param int|null $idLang
         * @param int|null $idShop (since 1.5.0) ID shop need to be used when we generate a product link for a product in a cart
         * @param int|null $idProductAttribute ID product attribute
         * @param bool $force_routes
         * @param bool $relativeProtocol
         * @param bool $withIdInAnchor
         * @param array $extraParams
         * @param bool $addAnchor
         *
         * @return string
         *
         * @throws PrestaShopException
         */
        //public function getProductLink($product,$alias = null,$category = null,$ean13 = null,$idLang = null,$idShop = null,$idProductAttribute = null,$force_routes = false,$relativeProtocol = false,$withIdInAnchor = false,$extraParams = [],bool $addAnchor = true)
        $sfproduct->link = $link->getProductLink($id_product);

        $cover = Product::getCover($id_product);
        $sfproduct->image = 'https://'.$link->getImageLink($psproduct['link_rewrite'], $cover['id_image'], $image_thump_size);                    

        //Start Category
        $categoryTree = "";
        $currentCategory = $allCategories[$psproduct['id_category_default']];
        while($currentCategory['id_category'] > 2)
        {
            $categoryTree.= $currentCategory['name'].'|';
            $currentCategory = $allCategories[$currentCategory['id_parent']];
        }
        $FullCategoryPath = implode(' > ', array_reverse(explode('|', substr($categoryTree, 0, -1))));
        $sfproduct->category = $FullCategoryPath;
        //End Category
    
        /**
        * Get product price
        *
        * @param integer $id_product Product id
        * @param boolean $tax With taxes or not (optional)
        * @param integer $id_product_attribute Product attribute id (optional)
        * @param integer $decimals Number of decimals (optional)
        * @param integer $divisor Useful when paying many time without fees (optional)
        * @return float Product price
        */
        //public static function getPriceStatic($id_product, $usetax = true, $id_product_attribute = NULL, $decimals = 6, $divisor = NULL, $only_reduc = false, $usereduc = true, 
        //                                      $quantity = 1, $forceAssociatedTax = false, $id_customer = NULL, $id_cart = NULL, $id_address_delivery = NULL)
        $sfproduct->price = Product::getPriceStatic($id_product,true,null,2);



        //Start Compinations                
        /**
        * Check if product has attributes combinaisons
        *
        * @return integer Attributes combinaisons number
        */

        /*
        $combinations_count = $clsProduct->hasAttributes();    
        if($combinations_count > 0)
        {
            $xml_feed = SeparateProductWithCombinations($xml_feed, $clsProduct, $link, $sfproduct);            
        }
        else
        {
            $xml_feed = AddProductToFeed($xml_feed, $sfproduct);   
        }
        */

        $xml_feed = AddProductToFeed($xml_feed, $sfproduct);

        //End Compinations                                 
    }
}

//Start output file and compress
file_put_contents($output_filename.'.xml', $xml_feed->asXML());
$zip = new ZipArchive;
if ($zip->open($output_filename.'.zip', ZipArchive::CREATE) === TRUE)
{    
    $zip->addFile($output_filename.'.xml', '');    
    $zip->close();
}
//End output file and compress

//Start Report Time
$time_end = microtime(true);
$execution_time = ($time_end - $time_start)/60;
echo '<b>Total Execution Time:</b> '.$execution_time.' Mins';
//End Start Report Time

function AddProductToFeed($xml_feed, $sfproduct)
{
    //Start add product to xml
    $xml_product = $xml_feed->addChild('product');
    $xml_product->addChild('sku', $sfproduct->sku);
    $xml_product->addChild('mpn', $sfproduct->mpn);
    $xml_product->addChild('ean', $sfproduct->ean);
    $xml_product->addChild('name', htmlspecialchars($sfproduct->name));
    $xml_product->addChild('category', $sfproduct->category);
    $xml_product->addChild('url', $sfproduct->url);
    $xml_product->addChild('image', $sfproduct->image);    
    $xml_product->addChild('price', $sfproduct->price);
    $xml_product->addChild('list_price', $sfproduct->list_price);
    $xml_product->addChild('quantity', $sfproduct->quantity);
    $xml_product->addChild('offer_from', $sfproduct->offer_from);
    $xml_product->addChild('offer_to', $sfproduct->offer_to);
    $xml_product->addChild('offer_price', $sfproduct->offer_price);
    $xml_product->addChild('offer_quantity', $sfproduct->offer_quantity);
    $xml_product->addChild('shipping_lead_time', $sfproduct->shipping_lead_time);
    //End add product to xml

    return $xml_feed;
}

function SeparateProductWithCombinations($xml_feed, $clsProduct, $link, $sfproduct)
{
    //$combinations_resume = $clsProduct->getAttributesResume($id_lang,'|','||');

    $combinations = $clsProduct->getAttributeCombinaisons($id_lang);
    //var_dump($combinations);
    /**
    * Get all available product attributes combinaisons
    *
    * @param integer $id_lang Language id
    * @return array Product attributes combinaisons
    */
    //$combinations = $clsProduct->getAttributeCombinations($id_lang);      
    foreach($combinations as $combination)
    {        
        $sfproduct_combination = new ShopflixProduct();
        $sfproduct_combination = clone $sfproduct;    
        if($combination['ean13'] == '')
        {
            $sfproduct_combination->sku = $combination['id_product'].'-'.$combination['id_product_attribute'];
        }
        else
        {
            $sfproduct_combination->sku = $combination['ean13'];			
        }
        
        if($combination['reference'] != '')
        {
            $sfproduct_combination->mpn = $combination['reference'];
        }
        if($combination['ean13'] != '')
        {
            $sfproduct_combination->ean = $combination['ean13'];
        }        

        /**
         * Create a link to a product.
         *
         * @param Product|array|int $product Product object (can be an ID product, but deprecated)
         * @param string|null $alias
         * @param string|null $category
         * @param string|null $ean13
         * @param int|null $idLang
         * @param int|null $idShop (since 1.5.0) ID shop need to be used when we generate a product link for a product in a cart
         * @param int|null $idProductAttribute ID product attribute
         * @param bool $force_routes
         * @param bool $relativeProtocol
         * @param bool $withIdInAnchor
         * @param array $extraParams
         * @param bool $addAnchor
         *
         * @return string
         *
         * @throws PrestaShopException
         */
        $sfproduct_combination->url = $link->getProductLink($combination['id_product'],null,null,null,null,null,$combination['id_product_attribute'],false,false,true); 
        
        /**
        * Get product price
        *
        * @param integer $id_product Product id
        * @param boolean $tax With taxes or not (optional)
        * @param integer $id_product_attribute Product attribute id (optional)
        * @param integer $decimals Number of decimals (optional)
        * @param integer $divisor Useful when paying many time without fees (optional)
        * @return float Product price
        */
        //public static function getPriceStatic($id_product, $usetax = true, $id_product_attribute = NULL, $decimals = 6, $divisor = NULL, $only_reduc = false, $usereduc = true, 
        //                                      $quantity = 1, $forceAssociatedTax = false, $id_customer = NULL, $id_cart = NULL, $id_address_delivery = NULL)
        $sfproduct_combination->price = Product::getPriceStatic($combination['id_product'], true, $combination['id_product_attribute'], 2);                        
        
        $images = getCombinationImageByIdNoLimit($combination['id_product_attribute'], $id_lang);
        //var_dump($sfproduct_combination);
        if(!is_null($images))
        {
            if(!empty($images))
            {
                $sfproduct_combination->image = array();   
                foreach($images as $image)
                {
                    if($image['id_image'] != $cover['id_image'])
                    {
                        $sfproduct_combination->image  = 'https://'.$link->getImageLink($sfproduct_combination['link_rewrite'], $image['id_image'], $image_thump_size); 
                    }
                }
            }
        }

        if(!empty($sfproduct_combination) && !is_null($sfproduct_combination))
        {
            //var_dump($sfproduct_combination);
            $xml_feed = AddProductToFeed($xml_feed, $sfproduct_combination);   
        }

    }
    return $xml_feed;
}

class ShopflixProduct
{
    public $sku;
    public $mpn;
    public $ean;
    public $name;
    public $category;
    public $url;
    public $image;
    public $price;
    public $list_price;
    public $quantity;
    public $offer_from;
    public $offer_to;
    public $offer_price;
    public $offer_quantity;
    public $shipping_lead_time;

    public function __construct()
    {
        $this->sku = '';
        $this->mpn = '';
        $this->ean = '';
        $this->name = '';
        $this->category = '';
        $this->url = '';
        $this->image = '';
        $this->price = 0.0;
        $this->list_price = 0.0;
        $this->quantity = 0;
        $this->offer_from = '';
        $this->offer_to = '';
        $this->offer_price = '';
        $this->offer_quantity = 0;
        $this->shipping_lead_time = 0;
    }
}

?>
