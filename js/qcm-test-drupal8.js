(function($, Drupal, drupalSettings){
    'use strict';
    Drupal.behaviors.jsTestD8 = {
        attach: function (context, settings) {

            // set properties
            var numAnsweredQuestions = [];
            //var countDownEnd = false;

            // get question IDs
            var questionIds = new Array();
            $(".test_d8-question").each(function(index, el){
                var id = $(this).attr("id").replace("test_d8-question", "");
                questionIds[index] = id;
            });

            // init: display first question
            var currentQuestion = 0;
            change_question(currentQuestion);

            // init: circle color (after page refresh)
            for (var i = 0, len = questionIds.length; i < len; i++){
                var qid = questionIds[i];
                if ($("input[name='propositions"+ qid +"']").is(':checked')){
                    circle_color(qid);
                    numAnsweredQuestions[qid] = 1;
                    //console.log('qid: '+qid);
                }
            }

            // navigation
            $("#test_d8-navisual").on("click", ".test_d8-navisual-item", function(e){
                e.preventDefault();
                var qid = $(this).data("qid"),
                    pos = $(this).data("pos");
                change_question(pos);
            });
            $("#test_d8-nav").on("click", "#test_d8-question-prev", function(e){
                e.preventDefault();
                change_question('prev');
            });
            $("#test_d8-nav").on("click", "#test_d8-question-next", function(e){
                e.preventDefault();
                change_question('next');
            });
            /*$(document).keyup(function(e){
                if (e.key === 37){
                    e.preventDefault();
                    change_question('prev');
                }
                if (e.key === 39){
                    e.preventDefault();
                    change_question('next');
                }
            });*/

            function enableSubmitButton(){
                if (numAnsweredQuestions.filter(Number).length == questionIds.length){
                    $("#test_d8-submit").prop("disabled", false);
                }
            }
            enableSubmitButton();

            // click radio button: apply color to circle + update cookie
            $("input[name^=propositions]").once('input-propositions').on("click", function(){
                var qid = $(this).attr("name").replace("propositions", "");
                circle_color(qid);
                numAnsweredQuestions[qid] = 1;
                enableSubmitButton();
            });

            function circle_color(qid){
                $("span.test_d8-navisual-item[data-qid='" + qid + "']").addClass("answered");
            }

            function change_question(num){
                if (num == 'prev'){
                    var prevQuestion = currentQuestion - 1;
                    if (questionIds[prevQuestion] !== undefined){
                        $("#test_d8-question" + questionIds[currentQuestion]).addClass("test_d8-hidden");
                        $("#test_d8-question" + questionIds[prevQuestion]).removeClass("test_d8-hidden");
                        currentQuestion = prevQuestion;
                        $("#test_d8-question-curr").text(currentQuestion + 1);
                    }
                } else if (num == 'next'){
                    var nextQuestion = currentQuestion + 1;
                    if (questionIds[nextQuestion] !== undefined){
                        $("#test_d8-question" + questionIds[currentQuestion]).addClass("test_d8-hidden");
                        $("#test_d8-question" + questionIds[nextQuestion]).removeClass("test_d8-hidden");
                        currentQuestion = nextQuestion;
                        $("#test_d8-question-curr").text(currentQuestion + 1);
                    }
                } else {
                    if (questionIds[num] !== undefined){
                        $("#test_d8-question" + questionIds[currentQuestion]).addClass("test_d8-hidden");
                        $("#test_d8-question" + questionIds[num]).removeClass("test_d8-hidden");
                        currentQuestion = num;
                        $("#test_d8-question-curr").text(currentQuestion + 1);
                    }
                }

                // disable prev/next button according to the current question
                if (questionIds[currentQuestion - 1] === undefined){
                    $("#test_d8-question-prev").prop("disabled", true);
                } else {
                    $("#test_d8-question-prev").prop("disabled", false);
                }
                if (questionIds[currentQuestion + 1] === undefined){
                    $("#test_d8-question-next").prop("disabled", true);
                } else {
                    $("#test_d8-question-next").prop("disabled", false);
                }

                return false;
            }

            // countdown timer
            $(context).find('#timer_qcm').once('timer_qcm').countdown({
                timestamp: (drupalSettings.TestD8.countdown * 1000),
                callback: function(weeks, days, hours, minutes, seconds){
                    var timeLeft = 0;
                    timeLeft += seconds + minutes*60 + hours*3600 + days*86400 + weeks*604800;
                    window.timeLeft = timeLeft;

                    // validate form if timer stops
                    if (weeks == 0 && days == 0 && hours == 0 && minutes == 0 && seconds == 0){
                        $("#test_d8-submit").prop('disabled', false).click();
                    }
                }
            });

        }
    };
})(jQuery, Drupal, drupalSettings);

