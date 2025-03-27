<?php
namespace App\Controllers;

use App\Models\BannerModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\RESTful\ResourceController;

class BannerController extends ResourceController
{
    use ResponseTrait;

    protected $model;
    protected $uploadPath        = WRITEPATH . 'uploads/banners/';
    protected $allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    protected $maxFileSize       = 2048; // 2MB in KB

    public function __construct()
    {
        $this->model = new BannerModel();
        helper('text');
    }

    public function options()
    {
        die('sssss');
        return $this->response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Headers', '*')
            ->setHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, DELETE')
            ->setStatusCode(200);
    }

    private function sendRespond($success, $message, $data = null, $statusCode = 200)
    {
        return $this->response
            ->setStatusCode($statusCode)
            ->setJSON([
                'success' => $success,
                'message' => $message,
                'data'    => $data,
            ]);
    }

    // GET /banner - List all active banners sorted by order
    public function index()
    {
        $banners = $this->model
            ->where('is_active', 1)
            ->orderBy('order', 'ASC')
            ->findAll();

        return $this->sendRespond(true, 'Banners retrieved', $banners);
    }

    // GET /banner/{id} - Get single banner
    public function show($id = null)
    {
        if (! $banner = $this->model->find($id)) {
            return $this->sendRespond(false, 'Banner not found', null, 404);
        }
        return $this->sendRespond(true, 'Banner retrieved', $banner);
    }

    // POST /banner - Create new banner
    public function create()
    {
        $rules = [
            'title'       => 'required|min_length[3]|max_length[255]',
            'description' => 'permit_empty|max_length[500]',
            'link'        => 'permit_empty|valid_url',
            'order'       => 'permit_empty|is_natural',
            'is_active'   => 'permit_empty|in_list[0,1]',
            'image'       => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return $this->sendRespond(false, 'Validation failed', $this->validator->getErrors(), 422);
        }

        try {
            $data = [
                'title'       => $this->request->getPost('title'),
                'description' => $this->request->getPost('description'),
                'link'        => $this->request->getPost('link'),
                'order'       => (int) ($this->request->getPost('order') ?? 0),
                'is_active'   => (int) ($this->request->getPost('is_active') ?? 1),
            ];

            // Handle both file upload and base64 string
            $image     = $this->request->getPost('image') ?? '';
            $imageFile = $this->request->getFile('image');

            if ($imageFile && $imageFile->isValid() && ! $imageFile->hasMoved()) {
                // Handle file upload
                $data['image'] = $this->handleImageUpload($imageFile);
            } elseif (preg_match('/^data:image\/(\w+);base64,/', $image, $matches)) {
                // Handle base64 string
                $data['image'] = $this->handleBase64Image($image, $matches[1]);
            } else {
                return $this->respond(false, 'Invalid image format', null, 422);
            }

            if (! $this->model->insert($data)) {
                return $this->sendRespond(false, 'Failed to create banner', null, 500);
            }

            return $this->sendRespond(
                true,
                'Banner created',
                $this->model->find($this->model->getInsertID()),
                201
            );

        } catch (\Exception $e) {
            return $this->sendRespond(false, $e->getMessage(), null, 500);
        }
    }

    // PUT /banner/{id} - Update banner
    public function update($id = null)
    {
        if (! $banner = $this->model->find($id)) {
            return $this->sendRespond(false, 'Banner not found', null, 404);
        }

        $rules = [
            'title'       => 'permit_empty|min_length[3]|max_length[255]',
            'description' => 'permit_empty|max_length[500]',
            'link'        => 'permit_empty|valid_url',
            'order'       => 'permit_empty|is_natural',
            'is_active'   => 'permit_empty|in_list[0,1]',
            'image'       => 'permit_empty|uploaded[image]|max_size[image,' . $this->maxFileSize . ']|is_image[image]',
        ];

        if (! $this->validate($rules)) {
            return $this->sendRespond(false, 'Validation failed', $this->validator->getErrors(), 422);
        }

        try {
            $data = $this->request->getJSON(true) ?? $this->request->getPost();

            // Handle image upload if provided
            if ($image = $this->request->getFile('image')) {
                $data['image'] = $this->handleImageUpload($image);
                $this->deleteOldImage($banner['image']);
            }

            if (! $this->model->update($id, $data)) {
                throw new \RuntimeException('Failed to update banner');
            }

            return $this->sendRespond(true, 'Banner updated', $this->model->find($id));

        } catch (\Exception $e) {
            return $this->sendRespond(false, $e->getMessage(), null, 500);
        }
    }

    // DELETE /banner/{id} - Delete banner
    public function delete($id = null)
    {
        if (! $banner = $this->model->find($id)) {
            return $this->sendRespond(false, 'Banner not found', null, 404);
        }

        try {
            $this->deleteOldImage($banner['image']);
            $this->model->delete($id);

            return $this->sendRespond(true, 'Banner deleted');

        } catch (\Exception $e) {
            return $this->sendRespond(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Handle image upload and return filename
     */
    private function handleImageUpload($image)
    {
        if (! $image->isValid() || $image->hasMoved()) {
            throw new \RuntimeException('Invalid image file');
        }

        if (! in_array($image->getMimeType(), $this->allowedImageTypes)) {
            throw new \RuntimeException('Only JPG, PNG, and WEBP images are allowed');
        }

        $newName = $image->getRandomName();
        $image->move($this->uploadPath, $newName);

        return 'uploads/banners/' . $newName;
    }

    /**
     * Delete old image file
     */
    private function deleteOldImage($imagePath)
    {
        if ($imagePath && file_exists(WRITEPATH . $imagePath)) {
            unlink(WRITEPATH . $imagePath);
        }
    }
}
