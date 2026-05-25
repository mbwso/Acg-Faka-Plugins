# 字体压缩教程

> 很多时候，我们喜欢的字体，体积都是非常大的，大到甚至可能十几兆，下面这个教程，教你将一个10M的字体，压缩至200K。

字体变大的原因：

- 由于字体里面有很多我们不需要的字体库，所以造成了体积较大

教程：

- 准备好Linux服务器，并且安装python3（一般无需安装，宝塔安装的时候已经安装了python3）
- 安装fontTools，Centos命令：`yum install fonttools`，Ubuntu命令：`apt-get install fonttools`
- 安装woff2，Centos命令：`yum install woff2`，Ubuntu命令：`apt-get install woff2`
- 下载需要处理的字体txt：<a href="/app/Plugin/Font/Wiki/sc_unicode.txt" target="_blank">sc_unicode.txt</a>，下载好和字体放到一起。
- 开始处理不需要的字体库，在你的字体目录执行命令：`pyftsubset 你的字体文件.ttf --unicodes-file=sc_unicode.txt`，执行完成后，会多出来一个`你的字体文件.subset.ttf`
  这样的文件，并且体积比原来的字体小80%;
- 将字体压缩并且转换成woff2网页字体文件，执行命令：`woff2_compress 你的字体文件.subset.ttf && ls -lah wqy-*`
  ，执行完成后，目录下会多出一个woff2后缀的字体文件，将他改成png格式，上传到本插件保存即可。