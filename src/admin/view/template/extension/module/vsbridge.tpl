<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right">
                <button type="submit" form="form-vsbridge" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
                <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
            <h1><?php echo $heading_title; ?></h1>
            <ul class="breadcrumb">
                <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
                <?php } ?>
            </ul>
        </div>
    </div>
    <div class="container-fluid">
        <?php if ($error_warning) { ?>
        <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3>
            </div>
            <div class="panel-body">
                <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-vsbridge" class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-status"><?php echo $entry_status; ?></label>
                        <div class="col-sm-10">
                            <select name="vsbridge_status" id="input-status" class="form-control">
                                <?php if ($vsbridge_status) { ?>
                                <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                                <option value="0"><?php echo $text_disabled; ?></option>
                                <?php } else { ?>
                                <option value="1"><?php echo $text_enabled; ?></option>
                                <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-secret-key"><?php echo $entry_secret_key; ?><div style="font-weight: normal;"><?php echo $info_secret_key; ?></div></label>
                        <div class="col-sm-10">
                            <div class="input-group">
                                <input type="text" name="vsbridge_secret_key" id="input_secret_key" value="<?php echo $vsbridge_secret_key; ?>" placeholder="<?php echo $entry_secret_key; ?>" id="input-secret-key" class="form-control" />
                                <span class="input-group-btn">
                                    <button class="btn btn-primary" type="button" id="generate_secret_key"><?php echo $button_generate_secret_key; ?></button>
                                </span>
                            </div>
                            <?php if ($error_secret_key) { ?>
                            <div class="text-danger"><?php echo $error_secret_key; ?></div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-entry-statuses"><?php echo $entry_endpoint_statuses; ?><div style="font-weight: normal;"><?php echo $info_endpoint_statuses; ?></div></label>
                        <div class="col-sm-10">
                            <?php foreach($vsbridge_endpoint_statuses as $eskey => $esvalue) { ?>
                            <div class="form-group">
                                <div class="col-sm-2">
                                    <?php echo $eskey; ?>
                                </div>
                                <div class="col-sm-10">
                                    <select name="vsbridge_endpoint_statuses[<?php echo $eskey; ?>]" class="form-control">
                                        <?php if ($esvalue) { ?>
                                        <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                                        <option value="0"><?php echo $text_disabled; ?></option>
                                        <?php } else { ?>
                                        <option value="1"><?php echo $text_enabled; ?></option>
                                        <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    // https://stackoverflow.com/questions/12635652/generate-a-secure-password-in-javascript
    String.prototype.pick = function(min, max) {
        var n, chars = '';

        if (typeof max === 'undefined') {
            n = min;
        } else {
            n = min + Math.floor(Math.random() * (max - min + 1));
        }

        for (var i = 0; i < n; i++) {
            chars += this.charAt(Math.floor(Math.random() * this.length));
        }

        return chars;
    };


    // Credit to @Christoph: http://stackoverflow.com/a/962890/464744
    String.prototype.shuffle = function() {
        var array = this.split('');
        var tmp, current, top = array.length;

        if (top) while (--top) {
            current = Math.floor(Math.random() * (top + 1));
            tmp = array[current];
            array[current] = array[top];
            array[top] = tmp;
        }

        return array.join('');
    };

    $( document ).ready(function() {
        $(document).on("click","#generate_secret_key",function() {
            var specials = '!@#$'; // Originally *&!@%^#$, but due to some bug I removed some characters
            var lowercase = 'abcdefghijklmnopqrstuvwxyz';
            var uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            var numbers = '0123456789';

            var all = specials + lowercase + uppercase + numbers;

            var password = '';
            password += specials.pick(2);
            password += lowercase.pick(2);
            password += uppercase.pick(2);
            password += all.pick(24);
            password = password.shuffle();
            $('#input_secret_key').val(password);
        });
    });
</script>
<?php echo $footer; ?>
