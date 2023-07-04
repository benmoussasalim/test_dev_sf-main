<?php

namespace App\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class HomeController extends AbstractController
{
    private $client;
    public function __construct(HttpClientInterface $client){
        $this->client = $client;
    }

    /**
     * @Route("/", name="homepage")
     * @param Request $request
     * @return Response
     */
    public function home(Request $request)
    {


        //recupere liens flux rss avec images

        /* $I=0;
        $ls = array();
         try {
            $c = curl_init();
            curl_setopt_array($c, Array(CURLOPT_URL => 'http://www.commitstrip.com/en/feed/',CURLOPT_RETURNTRANSFER => TRUE,));
            $d = curl_exec($c);curl_close($c);
            $x = simplexml_load_string($d, 'SimpleXMLElement', LIBXML_NOCDATA);
            $c=$x->channel;
            $n= count($x->channel->item);
            for($I=1; $I<$n;$I++){$h=$c->item[$I]->link;;${"ls"}[$I]=(string)$h[0];}
            for($I=1; $I<count($x->channel->item);$I++){
                if(!!substr_count((string)$c->item[$I]->children("content", true), 'jpg')<0){${"ls"}[$I] = "";}
                if(!!substr_count((string)$c->item[$I]->children("content", true), 'JPG')<0){${"ls"}[$I] = "";}
                if(!!substr_count((string)$c->item[$I]->children("content", true), 'GIF')<0){${"ls"}[$I] = "";}
                if(!!substr_count((string)$c->item[$I]->children("content", true), 'gif')<0){${"ls"}[$I] = "";}
                if(!!substr_count((string)$c->item[$I]->children("content", true), 'PNG')<0){${"ls"}[$I] = "";}
                if(!!substr_count((string)$c->item[$I]->children("content", true), '.png')<0){${"ls"}[$I] = "";}
            }
        } catch (\Exception $e) {
            // do nothing
        } */
        $tab = [];
        $flashbackMessage="";
        // récupèrer les données d'un flux RSS et ajoute les URLs d'images (si elles existent) au tableau
        try {
            $res = $this->apiCall('GET','aa');
            
            foreach($res->channel as $data){
                foreach($data->item as $item){
                    if(!empty((string) $item->enclosure['url']) && $this->isImageUrl((string) $item->enclosure['url'])){
                        $tab[] = (string) $item->enclosure['url'];
                    }
                }
            }
        } catch (\Exception $e){
            $flashbackMessage = "Erreur lors de la récupération : " . $e->getMessage();
        }

        //recpere liens api json avec image

        /*
        $j="";
        $h = @fopen("https://newsapi.org/v2/top-headlines?country=us&apiKey=c782db1cd730403f88a544b75dc2d7a0", "r");
        while ($b = fgets($h, 4096)) {$j.=$b;}
        $j=json_decode($j);
        for($II=$I+1; $II<count($j->articles);$II++){
            if($j->articles[$II]->urlToImage=="" || empty($j->articles[$II]->urlToImage) || strlen($j->articles[$II]->urlToImage)==0){continue;}
            $h=$j->articles[$II]->url;
            ${"ls2"}[$II]=$h;
        }
        */

       //Effectue une requête GET vers l'API "newsapi.org" pour récupérer les donnes. 
       try {
            $res = $this->client->request('GET', 'aa');
            foreach($res->toArray()['articles'] as $item){
                if(!empty($item['urlToImage']) && $this->isImageUrl($item['urlToImage'])){
                    $tab[] = $item['urlToImage'];
                }
            }
        
        } catch (\Exception $e){
            $flashbackMessage = "Erreur lors de la récupération des actualités : " . $e->getMessage();

        }

        
                //on fait un de doublonnage
                /*
                foreach($ls as $k=>$v){
                    if(empty($f))$f=array();
                    if($this->doublon($ls,$ls2)==false) $f[$k]=$v;
                }
                foreach($ls2 as $k2=>$v2){
                    if(empty($f))$f=array();
                    if($this->doublon($ls2,$ls)==false) $f[$k2]=$v2;
                }
                    */
                //recupere dans chaque url l'image
                /* $j=0;
                    $images=array();
                    while($j<count($f)){if(isset($f[$j])) {
                        try {$images[] = $this->recupereimagedanspage($f[$j]);} catch (\Exception $e) { /* erreur  }
                    } $j++;} */

        // Supprime les doublons des URLs d'images dans le tableau $tab.
        $tab = array_unique($tab);
        return $this->render('default/index.html.twig', array('images' => $tab,'flashbackMessage'=>$flashbackMessage));
    }



    /**
     * Effectue un appel d'API en utilisant le client HTTP Guzzle.
     * Si la réponse est au format XML, elle renvoie un objet SimpleXMLElement.
     * Sinon, elle renvoie la réponse brute.
     *
     * @param string $method La méthode HTTP pour l'appel (GET, POST, etc.).
     * @param string $url L'URL de l'API à appeler.
     * @return SimpleXMLElement|Response L'objet SimpleXMLElement si la réponse est XML, sinon la réponse brute.
     */
    private function apiCall($method,$url){    
        $res = $this->client->request($method,$url);
        if($this->isXml($res->getContent())){
            return simplexml_load_string($res->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        } else {
            return $res;
        }
    
    }

    /**
     * Vérifie si l'URL pointe vers une image en effectuant une requête GET
     * et en vérifiant le type de contenu dans l'en-tête de la réponse.
     *
     * @param string $url L'URL à vérifier.
     * @return bool Vrai si l'URL pointe vers une image, sinon faux.
     */
    private function isImageUrl($url): bool
    {
        try {
            $response = $this->client->request('GET',$url);
            $contentType = $response->getHeaders()['content-type'][0];
            return strpos($contentType, 'image/') === 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Vérifie si une chaîne donnée est au format XML en utilisant la fonction simplexml_load_string.
     *
     * @param string $value La chaîne à vérifier.
     * @return bool Vrai si la chaîne est au format XML, sinon faux.
     */
    private function isXml(string $value): bool{
        $prev = libxml_use_internal_errors(true);

        $doc = simplexml_load_string($value);
        $errors = libxml_get_errors();

        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return false !== $doc && empty($errors);
    }



        /*       
        private function recupereimagedanspage($l){
            if(strstr($l, "commitstrip.com"))
            {
                $doc = new \DomDocument();
                @$doc->loadHTMLFile($l);
                $xpath = new \DomXpath($doc);
                $xq = $xpath->query('//img[contains(@class,"size-full")]/@src');
                $src=$xq[0]->value;

                return $src;
            }
            else
            {
                $doc = new \DomDocument();
                @$doc->loadHTMLFile($l);
                $xpath = new \DomXpath($doc);
                $xq = $xpath->query('//img/@src');
                $src=$xq[0]->value;

                return $src;
            }
        }

        private function doublon($t1,$t2){
            foreach($t1 as $k1=>$v1){
                $doublon=0;
                foreach($t2 as $v2){if($v2==$v1){$doublon=1;}}
            }
            return $doublon;
        }
    */
   
}