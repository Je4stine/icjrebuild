<?php
class SupportController {
    public function contact() {
        ResponseHelper::success(null, 'Support request submitted successfully', 201);
    }
}
