<x-form-field name="name" label="Nama Penerbit" :value="$model->name" :required="true" maxlength="150" />
<x-form-field name="phone" label="Nomor Telepon" type="tel" :value="$model->phone" maxlength="30" col="col-md-6" />
<x-form-field name="email" label="Email" type="email" :value="$model->email" maxlength="255" col="col-md-6" />
<x-form-field name="address" label="Alamat" type="textarea" :value="$model->address" maxlength="500" />
