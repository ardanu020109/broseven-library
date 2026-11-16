<?php
$b = App\Models\Book::with('category','author','publisher','shelf')->where('title','Laskar Pelangi')->first();
echo $b->author->name.' | '.$b->category->name.' | '.$b->publisher->name.' | '.$b->shelf->code.PHP_EOL;

$u = App\Models\User::where('username','superadmin')->first();
echo $u->role->label().' | '.(Illuminate\Support\Facades\Hash::check('Password123!', $u->password) ? 'password OK' : 'password SALAH').PHP_EOL;

$f = App\Models\Book::factory()
    ->state(['category_id'=>1,'author_id'=>1,'publisher_id'=>1,'shelf_id'=>1])
    ->withStock(5,2)->make();
echo $f->total_stock.'/'.$f->available_stock.'/'.$f->borrowed_stock.PHP_EOL;
