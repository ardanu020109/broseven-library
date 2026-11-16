<x-form-field name="code" label="Kode Rak" :value="$model->code" :required="true" maxlength="20" col="col-md-4"
              help="Huruf, angka, strip, garis bawah. Otomatis huruf besar." />
<x-form-field name="name" label="Nama Rak" :value="$model->name" :required="true" maxlength="100" col="col-md-8" />
<x-form-field name="location" label="Lokasi" :value="$model->location" maxlength="150" placeholder="Contoh: Lantai 1, sayap kiri" />
<x-form-field name="description" label="Keterangan" type="textarea" :value="$model->description" maxlength="1000" />
