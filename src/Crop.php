<?php

namespace Encore\Cropper;

use Encore\Admin\Form\Field\ImageField;
use Encore\Admin\Form\Field\File;
use Encore\Admin\Admin;

class Crop extends File
{
    //use Field\UploadField;
    use ImageField;

    private $ratioW = 100;

    private $ratioH = 100;

    protected $view = 'laravel-admin-cropper::cropper';

    protected static $css = [
        '/vendor/laravel-admin-ext/cropper/cropper.min.css',
    ];

    protected static $js = [
        '/vendor/laravel-admin-ext/cropper/cropper.min.js',
        '/vendor/laravel-admin-ext/cropper/layer/layer.js'
    ];

    protected function preview()
    {
        return $this->objectUrl($this->value);
    }

    /**
     * [将Base64图片转换为本地图片并保存]
     * @E-mial wuliqiang_aa@163.com
     * @TIME   2017-04-07
     * @WEB    http://blog.iinu.com.cn
     * @param  [Base64] $base64_image_content [要保存的Base64]
     * @param  [目录] $path [要保存的路径]
     */
    private function base64_image_content($base64_image_content, $path)
    {
        //匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = $result[2];
            $new_file = $path . "/" . date('Ymd', time()) . "/";
            if (!file_exists($new_file)) {
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                mkdir($new_file, 0755, true);
            }
            $new_file = $new_file . md5(microtime()) . ".{$type}";
            if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                return $new_file;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function prepare($base64)
    {
        //检查是否是base64编码
        if (preg_match('/data:image\/.*?;base64/is',$base64)) {
            //base64转图片 返回的是绝对路径
            $imagePath = $this->base64_image_content($base64,public_path('uploads/base64img'));
            if ($imagePath !== false) {
                //删除旧图片
                @unlink(public_path('uploads/').$this->original);
                //处理图片地址
                preg_match('/base64img\/.*/is',$imagePath,$matches);

                $this->callInterventionMethods($imagePath);

                return $matches[0];
            } else {
                return 'lost';
            }
        } else {
            preg_match('/base64img\/.*/is',$base64,$matches);
            return isset($matches[0]) ? $matches[0] : $base64;
        }
    }


    public function cRatio($width,$height)
    {
        if (!empty($width) and is_numeric($width)) {
            $this->attributes['data-w'] = $width;
        } else {
            $this->attributes['data-w'] = $this->ratioW;
        }
        if (!empty($height) and is_numeric($height)) {
            $this->attributes['data-h'] = $height;
        } else {
            $this->attributes['data-h'] = $this->ratioH;
        }
        return $this;
    }

    public function render()
    {
        $this->name = $this->formatName($this->column);

        if (!empty($this->value)) {
            $this->value = filter_var($this->preview());
        }


        $title    = __('admin::cropper.cropper');
        $crop     = __('admin::cropper.crop');
        $original = __('admin::cropper.original');
        $empty    = __('admin::cropper.empty');

        $this->script = <<<EOT

//图片类型预存
var cropperMIME = '';

function getMIME(base64)
{
    var preg = new RegExp('data:(.*);base64','i');
    var result = preg.exec(base64);
    return result[1];
}

function cropper(imgSrc,id,w,h)
{
    
    var cropperImg = '<div id="cropping-div"><img id="cropping-img" src="'+imgSrc+'"><\/div>';
    
    
    //生成弹层模块
    layer.open({
        type: 1,
        skin: 'layui-layer-demo', //样式类名
        area: ['800px', '600px'],
        closeBtn: 2, //第二种关闭按钮
        anim: 2,
        resize: false,
        shadeClose: false, //关闭遮罩关闭
        title: '$title',
        content: cropperImg,
        btn: ['$crop','$original','$empty'],
        btn1: function(){
            var cas = cropper.getCroppedCanvas({
                width: w,
                height: h
            });
            //剪裁数据转换base64
            var base64url = cas.toDataURL(cropperMIME);
            //替换预览图
            $('#'+id+'-img').attr('src',base64url);
            //替换提交数据
            $('#'+id+'-input').val(base64url);
            //销毁剪裁器实例
            cropper.destroy();
            layer.closeAll('page');
        },
        btn2:function(){
            //默认关闭框
            //销毁剪裁器实例
            cropper.destroy();
        },
        btn3:function(){
            //清空表单和选项
            //销毁剪裁器实例
            cropper.destroy();
            layer.closeAll('page');
            //清空预览图
            $('#'+id+'-img').removeAttr('src');
            //清空提交数据
            $('#'+id+'-input').val('');
            //清空文件选择器
            $('#'+id+'-file').val('');
        }
    });

    var image = document.getElementById('cropping-img');
    var cropper = new Cropper(image, {
        aspectRatio: w / h,
        viewMode: 2,
    });
}

$('.cropper-btn').click(function(){
    var id = $(this).attr('data-id');
    $('#'+id+'-file').click();
});

//在input file内容改变的时候触发事件
$('.cropper-file').change(function(){
    var id = $(this).attr('data-id');
    var w = $(this).attr('data-w');
    var h = $(this).attr('data-h');
    
    //获取input file的files文件数组;
    //这边默认只能选一个，但是存放形式仍然是数组，所以取第一个元素使用[0];
    var file = $(this)[0].files[0];
    //创建用来读取此文件的对象
    var reader = new FileReader();
    //使用该对象读取file文件
    reader.readAsDataURL(file);
    //读取文件成功后执行的方法函数
    reader.onload = function(e){
        //选择所要显示图片的img，要赋值给img的src就是e中target下result里面的base64编码格式的地址
        $('#'+id+'-img').attr('src',e.target.result);
        cropperMIME = getMIME(e.target.result);
        //调取剪切函数（内部包含了一个弹出框）
        cropper(e.target.result,id,w,h);
        $('#'+id+'-input').val(e.target.result);
    };
});

//点击图片触发弹层
$('.cropper-img').click(function(){
    var id = $(this).attr('data-id');
    var w = $(this).attr('data-w');
    var h = $(this).attr('data-h');
    cropper($(this).attr('src'),id,w,h);
});

EOT;

        if (!$this->display) {
            return '';
        }

        Admin::script($this->script);

        return view($this->getView(), $this->variables());
    }

}
