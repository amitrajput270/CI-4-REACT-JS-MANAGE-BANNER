<?php
namespace App\Controllers;

use App\Models\BannerModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\RESTful\ResourceController;

class BannerController extends ResourceController
{
    use ResponseTrait;

    protected $model;

    private $rules = [
        'title'       => 'required|min_length[3]|max_length[255]',
        'description' => 'permit_empty|max_length[255]',
        'order'       => 'permit_empty|is_natural|intval',
        'is_active'   => 'permit_empty|in_list[0,1]',
        'link'        => 'permit_empty|required_without[image]',
    ];

    public function __construct()
    {
        $this->model = new BannerModel();
    }

    private function sendResponse($success, $message, $data = null, $statusCode = 200)
    {
        return $this->response->setStatusCode($statusCode)->setJSON([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    private function mapRequest($parameters = [])
    {
        $allVariable = [
            'title',
            'description',
            'order',
            'is_active',
            'link',
            'image',
        ];
        $data = [];
        foreach ($allVariable as $variable) {
            $data[$variable] = null;
        }
        foreach ($parameters as $key => $value) {
            if (in_array($key, $allVariable)) {
                $data[$key] = $value;
            }
        }
        return (object) $data;
    }

    private function mapImageParameters($params)
    {
        $image = null;
        $link  = null;
        if (preg_match('/^http(s)?:\/\//', $params->link) && preg_match('/^http(s)?:\/\//', $params->image)) {
            $link = $params->link;
        } elseif (! str_contains($params->image, ';base64,') && preg_match('/^http(s)?:\/\//', $params->link)) {
            if (! empty($params->image) && file_exists(FCPATH . 'uploads/' . $params->image)) {
                unlink(FCPATH . 'uploads/' . $params->image);
            }
            $link  = $params->link;
            $image = null;
        }
        if (str_contains($params->image, ';base64,')) {
            $image = $params->image;
        }
        if (str_contains($params->link, ';base64,')) {
            $image = $params->link;
        }
        if (! empty($params->image) && empty($params->link)) {
            $image = $params->image;
        }

        if (! empty($link)) {
            $this->rules['link'] = 'permit_empty|required_without[image]';
        }
        if (! $link && ! $image) {
            return [
                'success' => false,
                'message' => 'Please provide either an image or a link',
                'data'    => null,
            ];
        }
        return [
            'success' => true,
            'data'    => ['link' => $link, 'image' => $image],
        ];
    }

    public function index()
    {
        $banners = $this->model->orderBy('order', 'ASC')->findAll();
        $banners = array_map(function ($banner) {
            if ($banner['image']) {
                $banner['imagePath'] = 'uploads';
            }
            return $banner;
        }, $banners);

        return $this->sendResponse(true, 'Banners retrieved successfully', $banners);
    }

    public function show($id = null)
    {
        if (! $banner = $this->model->find($id)) {
            return $this->sendResponse(false, 'Banner not found', null, 404);
        }
        return $this->sendResponse(true, 'Banner retrieved successfully', $banner);
    }

    public function create()
    {
        $data  = $this->mapRequest((array) $this->request->getJSON());
        $image = null;
        if (! isset($data->link) || empty($data->link)) {
            if (! isset($data->image) || empty($data->image)) {
                return $this->sendResponse(false, 'Please provide either an image or a link', null, 400);
            }
        }
        if (isset($data->link) && ! empty($data->link)) {
            if (preg_match('/^http(s)?:\/\//', $data->link)) {
                $this->rules['link'] = 'permit_empty|required_without[image]';
                $data->image         = null;
            } else {
                $data->image = $data->link;
                $data->link  = null;
            }
        }

        if (isset($data->image) && ! empty($data->image)) {
            $this->rules['image'] = 'permit_empty|required_without[link]';
        }
        if (! $this->validate($this->rules)) {
            return $this->sendResponse(false, 'Failed to create banner', $this->validator->getErrors(), 400);
        }
        if (isset($data->image) && ! empty($data->image)) {
            $base64String = $data->image;
            $imageParts   = explode(";base64,", $base64String);
            if (count($imageParts) !== 2) {
                return $this->sendResponse(false, 'Invalid Base64 data', null, 400);
            }
            $imageTypeAux = explode("image/", $imageParts[0]);
            $imageType    = $imageTypeAux[1] ?? 'png';
            $imageData    = base64_decode($imageParts[1]);

            if (! $imageData) {
                return $this->sendResponse(false, 'Invalid Base64 data', null, 400);
            }
            $image      = uniqid() . '.' . $imageType;
            $uploadPath = FCPATH . 'uploads/' . $image;

            if (! file_put_contents($uploadPath, $imageData)) {
                return $this->sendResponse(false, 'Failed to save image', null, 500);
            }
        }
        $this->model->insert([
            'title'       => $data->title,
            'image'       => $image,
            'description' => $data->description ?? '',
            'order'       => $data->order ?? 0,
            'is_active'   => $data->is_active ?? 1,
            'link'        => $data->link ?? '',
        ]);
        return $this->sendResponse(true, 'Banner created successfully', $this->model->find($this->model->getInsertID()), 201);
    }

    public function update($id = null)
    {
        $data  = $this->mapRequest((array) $this->request->getJSON());
        $image = null;
        if (! $banner = $this->model->find($id)) {
            return $this->sendResponse(false, 'Banner not found', null, 404);
        }
        if (! isset($data->link) || empty($data->link)) {
            if (! isset($data->image) || empty($data->image)) {
                return $this->sendResponse(false, 'Please provide either an image or a link', null, 400);
            }
        }

        $mapImageParametersResponse = $this->mapImageParameters($data);

        if (! $mapImageParametersResponse['success']) {
            return $this->sendResponse(false, $mapImageParametersResponse['message'], null, 400);
        }

        $data->link  = $mapImageParametersResponse['data']['link'];
        $data->image = $mapImageParametersResponse['data']['image'];

        // print_r($data);die();

        if (isset($data->image) && ! empty($data->image)) {
            $this->rules['image'] = 'permit_empty|required_without[link]';
        }
        if (! $this->validate($this->rules)) {
            return $this->sendResponse(false, 'Failed to update banner', $this->validator->getErrors(), 400);
        }
        if (isset($data->image) && ! empty($data->image)) {
            if (! str_contains($data->image, ';base64,')) {
                $image = $data->image;
                if ($banner['image'] && $banner['image'] !== $image && file_exists(FCPATH . 'uploads/' . $banner['image'])) {
                    unlink(FCPATH . 'uploads/' . $banner['image']);
                }
            } else {
                $base64String = $data->image;
                $imageParts   = explode(";base64,", $base64String);
                if (count($imageParts) !== 2) {
                    return $this->sendResponse(false, 'Invalid Base64 data', null, 400);
                }
                $imageTypeAux = explode("image/", $imageParts[0]);
                $imageType    = $imageTypeAux[1] ?? 'png';
                $imageData    = base64_decode($imageParts[1]);
                if (! $imageData) {
                    return $this->sendResponse(false, 'Invalid Base64 data', null, 400);
                }
                $image      = uniqid() . '.' . $imageType;
                $uploadPath = FCPATH . 'uploads/' . $image;
                if (! file_put_contents($uploadPath, $imageData)) {
                    return $this->sendResponse(false, 'Failed to save image', null, 500);
                }
                if ($banner['image'] && file_exists(FCPATH . 'uploads/' . $banner['image'])) {
                    unlink(FCPATH . 'uploads/' . $banner['image']);
                }
            }
        }
        $this->model->update($id, [
            'title'       => $data->title,
            'image'       => $image,
            'description' => $data->description ?? $banner['description'],
            'order'       => $data->order ?? $banner['order'],
            'is_active'   => $data->is_active ?? $banner['is_active'],
            'link'        => $data->link,
        ]);
        return $this->sendResponse(true, 'Banner updated successfully', $this->model->find($id));
    }

    public function delete($id = null)
    {
        try {
            if (! $this->model->find($id)) {
                return $this->sendResponse(false, 'Banner not found', null, 404);
            }
            $this->model->delete($id);
            return $this->sendResponse(true, 'Banner deleted successfully');
        } catch (\Exception $e) {
            return $this->sendResponse(false, 'Failed to delete banner', $e->getMessage(), 400);
        }
    }
}
