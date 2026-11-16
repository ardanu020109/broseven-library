<x-form-field name="name" label="Nama Penulis" :value="$model->name" :required="true" maxlength="150" />
<x-form-field name="biography" label="Biografi" type="textarea" :value="$model->biography" rows="5" maxlength="5000" />
<x-form-image name="photo" label="Foto" :current="$model->photo_url" :removable="(bool) $model->photo" />
