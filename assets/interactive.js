$(function () {
    function APIError(code, message) {
        this.code = code;
        this.message = message;
    }

    APIError.prototype.toString = function () {
        return "APIError " + this.code + ": " + this.message;
    };

    function default_api_error_handler(data, code) {
        throw new APIError(code, data);
    }

    function add_get_param(url, name, val) {
        name = encodeURIComponent(name);
        val = encodeURIComponent(val);

        var separator = url.match(/\?/) === null ? "?" : "&";
        return url + separator + name + "=" + val;
    }

    function add_get_params(url, params) {
        for (var k in params) if (Object.prototype.hasOwnProperty.call(params, k))
            url = add_get_param(url, k, params[k]);
        return url;
    }

    function query_api(method, url, arguments, handler, error_handler) {
        url = add_get_params(url, arguments);
        error_handler = error_handler || default_api_error_handler;

        var xhr = new XMLHttpRequest();

        function wrap_handler(handler) {
            return function () {
                return handler(xhr.response, xhr.status, xhr);
            }
        }

        xhr.addEventListener("load", wrap_handler(handler));
        xhr.addEventListener("error", wrap_handler(error_handler));
        xhr.open(method, url);
        xhr.responseType = "json";
        xhr.send();
    }

    function Autocomplete() {
        var self = this;

        this.root = $("<div>")
            .addClass("autocomplete-root");
        this.input = $("<input>")
            .attr("type", "text")
            .appendTo(this.root)
            .on("keyup", function (ev) {
                if (ev.key === "ArrowDown") self.change_selection(1);
                if (ev.key === "ArrowUp") self.change_selection(-1);

                self.change_listener();
            })
            .on("keydown", function (ev) {
                if (ev.key === "Tab") {
                    if (self.complete_current())
                        ev.preventDefault();
                }
            })
            .on("blur", function (ev) {
                var visible = false;
                if (ev.relatedTarget) {
                    visible = $(ev.relatedTarget).closest(".autocomplete-root").get(0) === self.root.get(0);
                }
                self.set_visible(visible);
            });
        this.options = $("<ul>").appendTo(this.root)
            .on("mouseout", function () {
                self.mark_active_by_el(null);
            });

        this.abort = function () {};
        this.get_suggestions = function (_, cb) { cb([]); };
    }

    Autocomplete.prototype.set_visible = function (visible) {
        this.root.toggleClass("show-suggestions", !!visible);
    };

    Autocomplete.prototype.mark_active_by_el = function (el) {
        this.options.find("li").each(function () {
            var $cur = $(this);
            $cur.toggleClass("active", this === el);
        });
    };

    Autocomplete.prototype.change_listener = function () {
        var self = this;

        this.abort();

        var input_text = this.input.val();
        if (input_text === "") {
            this.set_visible(false);
            return;
        }

        this.get_suggestions(input_text, function (suggestions) {
            console.log(suggestions);
            suggestions = Array.prototype.slice.call(suggestions, 0);
            suggestions.sort();
            var items = suggestions.map(function (s) {
                var found;
                self.options.find("li").each(function () {
                    var el = $(this);
                    if (el.text() === s) {
                        found = el;
                        return false;
                    }
                });

                if (found)
                    return found;

                return $("<li>").text(s).attr("tabindex", 0)
                    .on("click", function (ev) {
                        ev.preventDefault();
                        self.apply_completion($(this).text());
                    })
                    .on("mouseover", function () {
                        self.mark_active_by_el(this);
                    });
            });
            self.options.empty();
            for (var i = 0; i < items.length; i++)
                self.options.append(items[i]);

            if (!self.options.find("li.active").length)
                self.options.find("li").first().addClass("active");

            self.set_visible(self.options.find("li").length > 0);
        });
    };

    Autocomplete.prototype.change_selection = function (d) {
        var lis = this.options.find("li");
        if (lis.length === 0)
            return;

        var idx = lis.index(lis.filter(".active"));
        if (idx < 0)
            idx = 0;
        idx += d;
        idx %= lis.length;
        lis.removeClass("active");
        lis.eq(idx).addClass("active");
    };

    Autocomplete.prototype.complete_current = function () {
        var cur = this.options.find("li.active");
        if (cur.length !== 1)
            return false;

        this.apply_completion(cur.text());
        this.change_listener();
        return true;
    };

    Autocomplete.prototype.apply_completion = function (text) {
        this.input.val(text);
    };

    var Tags = (function () {
        var tags;

        function get(callback) {
            if (tags !== undefined) {
                callback(tags);
                return;
            }

            query_api("GET", "/api/tags", {}, function (resp) {
                if (Object.prototype.toString.call(resp) !== "[object Array]") {
                    throw "Unexpeced return value from /api/tags";
                }
                tags = resp;
                callback(tags);
            });
        }

        return {
            get: get,
            clear_cache: function () { tags = null; }
        };
    })();

    function selectedTag(tag) {
        return $("<div>")
            .addClass("tag")
            .text(tag)
            .append($("<input>")
                .attr({
                    name: "tag[]",
                    type: "hidden",
                })
                .val(tag)
            )
            .prepend($("<button>")
                .attr("type", "button")
                .addClass("delete")
                .text("X")
                .on("click", function () {
                    $(this).closest(".tag").remove();
                })
            );
    }

    $("fieldset.tags").each(function () {
        var $this = $(this);
        var label = $this.find("legend").text();

        $this.find("input").map(function () { return $(this).val(); }).get().filter(x => !!x);

        var out = $("<div>")
            .addClass("tag-input labelled")
            .append($("<label>").text(label).attr("for", "tag-input-field"));
        var content = $("<div>").addClass("labelled-input").appendTo(out);
        var tag_list = $("<div>").append($this.find("input")
            .filter(function () {
                return !!$(this).val();
            })
            .map(function () {
                return selectedTag($(this).val());
            }).get()
        );
        content.append(tag_list);

        var ac = new Autocomplete();
        ac.get_suggestions = function (text, callback) {
            text = String.prototype.toLowerCase.call(text);
            Tags.get(function (tags) {
                console.log(["tags", tags]);
                tags = Array.prototype.filter.call(tags, function (it) {
                    it = String.prototype.toLowerCase.call(it);
                    return it.indexOf(text) > -1;
                });
                console.log(["filtered", tags]);
                callback(tags);
            });
        };
        ac.apply_completion = function (text) {
            ac.input.val("");
            tag_list.append(selectedTag(text));
        };

        ac.input
            .attr({
                name: "tag[]",
                id: "tag-input-field",
                placeholder: "Another tag"
            })
            .addClass("tag-user-input")
            .on("keydown", function (ev) {
                if (ev.key === "Enter") {
                    ev.preventDefault();

                    if (ev.ctrlKey) {
                        $(this).closest("form").each(function () {
                            this.submit();
                        });
                        return;
                    }

                    var tag = $(this).val().trim();
                    if (tag === "")
                        return;

                    tag_list.append(selectedTag(tag));
                    $(this).val("");
                } else if (ev.key === "Backspace") {
                    if ($(this).val() === "") {
                        tag_list.find(".tag").last().remove();
                    }
                }
            });
        content.append(ac.root);

        $this.after(out).remove();
    });

    $("button.confirm").on("click", function (ev) {
        if (!window.confirm($(this).data("question")))
            ev.preventDefault();
    });

    function updateAttachmentDeletionClass(tr) {
        tr = $(tr);
        tr.toggleClass("delete", tr.find(".attachment-delete-checkbox").eq(0).prop("checked"));
    }

    function attachmentDeleteCheckbox(tr) {
        $(tr).each(function () {
            updateAttachmentDeletionClass(this);
        }).find(".attachment-delete-checkbox").on("change", function () {
            updateAttachmentDeletionClass($(this).closest("tr"));
        });
    }

    attachmentDeleteCheckbox($(".attachments tbody tr"));
});