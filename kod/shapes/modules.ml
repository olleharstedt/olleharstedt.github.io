module Surface = struct
    type t
end

(* companion_cube *)

module type SHAPE = sig
  type t
  val area : t -> float
  val make : unit -> t
end

type any_shape = Any : (module SHAPE with type t = 'a) * 'a -> any_shape

module Point : SHAPE = struct
    type t = int * int
    let area t = 0.
    let make () = (1, 2)
end

module Rect : SHAPE = struct
  type t = Point.t * Point.t
  let area t = 1.
  let make () = (Point.make (), Point.make ())
end

module Circle : SHAPE = struct
  type t = {radius: int}
  let area {radius=r} = Float.pi *. (float r) ** 2.
  let make () = {radius = 10}
end

let circle1 = Circle.make ()
let rect1 = Rect.make ()

let l = [Any ((module Circle), circle1); Any ((module Rect), rect1)]
