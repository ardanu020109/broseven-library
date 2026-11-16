<?php

namespace App\Http\Requests;

class UpdateBookRequest extends StoreBookRequest
{
    protected function currentBookId(): ?int
    {
        return $this->route('book')?->id;
    }
}
