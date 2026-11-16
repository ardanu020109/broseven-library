<x-form-field name="name" label="Nama Kategori" :value="$model->name" :required="true" maxlength="100" col="col-md-6" />
<x-form-field name="slug" label="Slug" :value="$model->slug" maxlength="120" col="col-md-6"
              help="Dikosongkan = dibuat otomatis dari nama." />
<x-form-field name="description" label="Deskripsi" type="textarea" :value="$model->description" maxlength="1000" />
